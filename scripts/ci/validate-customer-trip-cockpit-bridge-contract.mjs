import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';
import process from 'node:process';

const root = resolve(import.meta.dirname, '..', '..');
const vip = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip');
const paths = {
  controller: join(vip, 'class-tra-vel-customer-trip-cockpit-controller.php'),
  store: join(vip, 'class-tra-vel-customer-trip-cockpit-store.php'),
  provider: join(vip, 'interface-tra-vel-customer-trip-cockpit-read-model-provider.php'),
  principal: join(vip, 'class-tra-vel-traveler-principal.php'),
  customerFactory: join(vip, 'class-tra-vel-customer-trip-cockpit-customer-view-factory.php'),
  bootstrap: join(vip, 'bootstrap.php'),
  plugin: join(root, 'plugin', 'tra-vel-agent-core', 'tra-vel-agent-core.php'),
  health: join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-agent-controller.php'),
  uninstall: join(root, 'plugin', 'tra-vel-agent-core', 'uninstall.php'),
  assets: join(root, 'theme', 'tra-vel-v2', 'inc', 'assets.php'),
  script: join(root, 'theme', 'tra-vel-v2', 'assets', 'js', 'customer-trip-cockpit.js'),
  template: join(root, 'theme', 'tra-vel-v2', 'page-saved.php'),
  styles: join(root, 'theme', 'tra-vel-v2', 'assets', 'css', 'app.css'),
};

const failures = [];
const source = {};
const expect = (condition, message) => { if (!condition) failures.push(message); };
const includes = (area, marker, message) => expect(source[area]?.includes(marker), message);
const count = (value, pattern) => (value.match(pattern) || []).length;

for (const [name, file] of Object.entries(paths)) {
  try {
    source[name] = readFileSync(file, 'utf8');
  } catch (error) {
    source[name] = '';
    failures.push(`${name} is missing or unreadable: ${error.message}`);
  }
}

// The public bridge is deliberately one read-only, non-parameterized route.
expect(count(source.controller, /register_rest_route\s*\(/g) === 1, 'Trip Cockpit controller must register exactly one REST route.');
includes('controller', "$this->rest_base        = 'customer-trip-cockpit/current';", 'Trip Cockpit route must remain the non-parameterized current route.');
includes('controller', "'methods'             => WP_REST_Server::READABLE", 'Trip Cockpit route must remain GET/readable only.');
includes('controller', "'callback'            => array( $this, 'get_current' )", 'Current route must use the customer-view callback.');
includes('controller', "'permission_callback' => array( $this, 'can_read' )", 'Current route must authorize before materializing a view.');
for (const forbidden of ['WP_REST_Server::CREATABLE', 'WP_REST_Server::EDITABLE', 'WP_REST_Server::DELETABLE', '__return_true']) {
  expect(!source.controller.includes(forbidden), `Trip Cockpit controller exposes or permits a forbidden REST boundary: ${forbidden}.`);
}
expect(!/customer-trip-cockpit\/[^'"\s]*\(\?P</.test(source.controller), 'Trip Cockpit route must not accept a reference in its URL.');
expect(!/function\s+(?:create|update|delete|commit|write|mutate|reserve|book|pay|cancel|refund)_/i.test(source.controller), 'Trip Cockpit controller must expose no mutation callback.');
for (const forbidden of ['commit_server_source(', 'Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection(', 'get_json_params(', 'get_body_params(', 'get_param(', 'set_param(']) {
  expect(!source.controller.includes(forbidden), `Trip Cockpit REST request must not supply a private source or projection through ${forbidden}.`);
}

// Requests are closed: no query/body, an explicit read intent, and one exact mode.
for (const marker of [
  "get_header( 'X-Tra-Vel-Cockpit-Mode' )",
  "get_header( 'X-Tra-Vel-Cockpit-Read' )",
  "$request->get_query_params()",
  "$request->get_body()",
  "! empty( $query ) || '' !== $body || '1' !== $intent",
  "array( 'signed-in', 'scoped-session' )",
  "'signed-in' === $mode ? $this->authorize_signed_in( $request ) : $this->authorize_scoped_session( $request )",
]) includes('controller', marker, `Closed Trip Cockpit read contract is missing ${marker}.`);
includes('controller', "$this->same_site_read( $request )", 'Trip Cockpit reads must pass the same-site browser check.');
includes('controller', "get_header( 'Sec-Fetch-Site' )", 'Trip Cockpit reads must reject an explicit cross-site fetch.');
includes('controller', "get_header( 'Origin' )", 'Trip Cockpit reads must verify an explicit Origin.');

// Signed-in mode requires the WordPress owner, capability, and exact REST nonce.
for (const marker of [
  'get_current_user_id()',
  "get_header( 'X-WP-Nonce' )",
  "wp_verify_nonce( $nonce, 'wp_rest' )",
  "current_user_can( 'read' )",
  'get_owned_current_projection( $owner_user_id, time() )',
  "'mode' => 'signed_in'",
  "'owner_scope_digest' => $expected_scope",
]) includes('controller', marker, `Signed-in Trip Cockpit authorization is missing ${marker}.`);

// Scoped mode gets its bearer only from the hardened HttpOnly capability cookie.
for (const marker of [
  'Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE',
  '$_COOKIE[ $name ]',
  'rawurldecode( wp_unslash(',
  "preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $value )",
  '$this->capability_store->current_session( $session_value, time() )',
  "'trip_view_redacted'",
  "'trip_redacted'",
  '$this->capability_store->resolve_scoped_session(',
  "'mode' => 'scoped_session'",
  "'owner_scope_digest' => null",
]) includes('controller', marker, `Scoped Trip Cockpit authorization is missing ${marker}.`);
expect(!source.controller.includes("get_header( 'Authorization' )"), 'Trip Cockpit bridge must not accept its capability bearer in an Authorization header.');
expect(!source.controller.includes("get_header( 'Cookie' )"), 'Trip Cockpit bridge must use the server cookie jar, not a caller-authored Cookie header.');

// A scoped view is a whole-trip binding: exact trip/account, explicitly null case.
for (const marker of [
  "null !== $session['case_ref'] || null === $session['account_ref']",
  "$this->provider->get_bound_projection( $session['trip_ref'], null, $session['account_ref'], time() )",
  "null !== $record['case_ref']",
  "hash_equals( $session['trip_ref'], $record['trip_ref'] )",
  "hash_equals( $session['account_ref'], $record['account_ref'] )",
  "array( 'trip_ref' => $record['trip_ref'], 'case_ref' => null, 'account_ref' => $record['account_ref'] )",
]) includes('controller', marker, `Exact trip/account/null-case capability binding is missing ${marker}.`);
includes('store', 'if ( null !== $case_ref ||', 'Read-model provider must reject any case-bound whole-trip lookup.');
includes('store', 'WHERE trip_ref = %s AND account_ref = %s AND retention_until >= %s LIMIT 1', 'Scoped read must constrain trip and account in the database predicate.');
for (const method of ['get_owned_current_projection', 'get_bound_projection', 'consume_limit']) {
  includes('provider', `function ${method}(`, `Trusted read-model provider is missing ${method}.`);
}

// Only the validated customer factory may cross the private-to-browser boundary.
includes('controller', 'Tra_Vel_Customer_Trip_Cockpit_Customer_View_Factory::create_view(', 'Controller must materialize the response with the customer-view factory.');
includes('controller', "$authorized['projection']", 'Controller must pass only its pre-authorized server-side projection to the customer-view factory.');
includes('customerFactory', 'Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection(', 'Customer-view factory must revalidate the private projection seal.');
includes('controller', "unset( $this->authorized[ $key ] );", 'Cached private authorization material must be single-use within a request.');
for (const unsafeReturn of ['private_response( $authorized', 'private_response( $record', "rest_ensure_response( $record['projection']", "new WP_REST_Response( $record['projection']"]) {
  expect(!source.controller.includes(unsafeReturn), `Controller must never serialize the private read model: ${unsafeReturn}.`);
}

// Durable storage is three transactional tables with duplicated bindings and race indexes.
expect(count(source.store, /dbDelta\s*\(/g) === 3, 'Trip Cockpit store must install exactly three tables.');
expect(count(source.store, /ENGINE=InnoDB/g) === 3, 'All three Trip Cockpit tables must require InnoDB.');
for (const marker of [
  'tra_vel_customer_trip_cockpits',
  'tra_vel_customer_trip_cockpit_revisions',
  'tra_vel_customer_trip_cockpit_limits',
  'owner_user_id bigint(20) unsigned NOT NULL',
  'account_ref varchar(112) NOT NULL',
  'cockpit_ref varchar(112) NOT NULL',
  'trip_ref varchar(112) NOT NULL',
  'owner_scope_digest char(64) NOT NULL',
  'previous_projection_digest char(64) DEFAULT NULL',
  'projection_digest char(64) NOT NULL',
  'UNIQUE KEY trip_ref (trip_ref)',
  'UNIQUE KEY cockpit_ref (cockpit_ref)',
  'KEY owner_trip (owner_user_id,trip_ref)',
  'KEY account_trip (account_ref,trip_ref)',
  'UNIQUE KEY cockpit_revision (cockpit_ref,revision)',
  'KEY owner_trip_revision (owner_user_id,trip_ref,revision)',
  'UNIQUE KEY limit_key (limit_key)',
  'KEY expiry (expires_at,id)',
]) includes('store', marker, `Trip Cockpit schema is missing required binding/index ${marker}.`);
for (const marker of [
  "'expected_tables' => count( $requirements )",
  "'transactional_tables' => $transactional",
  "'required_indexes_ready' => $ready_indexes === $required_indexes",
  "'tables_ready' => $ready === count( $requirements ) && $transactional === count( $requirements ) && $ready_indexes === $required_indexes",
]) includes('store', marker, `Trip Cockpit schema health must fail closed on ${marker}.`);
for (const marker of [
  'READINESS_CACHE_OPTION',
  'READINESS_CACHE_TTL_SECONDS = 300',
  'private static function readiness_record()',
  'private static function readiness_cache_valid(',
  'public static function invalidate_readiness_cache()',
  "empty( $record['health']['tables_ready'] )",
  "empty( $record['repair_attempted'] )",
]) includes('store', marker, `Trip Cockpit readiness caching/repair is missing ${marker}.`);
const tableSql = (source.store.match(/dbDelta\([\s\S]*?ENGINE=InnoDB[\s\S]*?\);/g) || []).join('\n');
expect(!/\b(?:email|phone|mobile|passport|identity_number|card_number|cvv|cvc|medical_note|provider_payload|supplier_payload|access_token|bearer_token)\b/i.test(tableSql), 'Trip Cockpit tables must not add raw identity, payment, medical, provider, or bearer columns.');

// Revisions are append-only and every successor is ancestry-checked under a lock.
for (const marker of [
  "'START TRANSACTION'",
  'FOR UPDATE',
  "1 !== (int) $projection['revision'] || null !== $projection['previous_projection_digest']",
  'Tra_Vel_Customer_Trip_Cockpit_Policy::assert_successor( $previous, $projection, $timestamp )',
  'AND revision = %d AND projection_digest = %s',
  '$this->insert_revision(',
  "'ROLLBACK'",
  "'COMMIT'",
]) includes('store', marker, `Immutable Trip Cockpit successor path is missing ${marker}.`);
const revisionWriterStart = source.store.indexOf('private function insert_revision(');
const revisionWriterEnd = source.store.indexOf('\n\t/** Revalidate every duplicated binding', revisionWriterStart);
const revisionWriter = revisionWriterStart >= 0 ? source.store.slice(revisionWriterStart, revisionWriterEnd > revisionWriterStart ? revisionWriterEnd : undefined) : '';
expect(revisionWriter.includes('$wpdb->insert(') && !/\$wpdb->(?:update|replace|delete)\s*\(/.test(revisionWriter), 'Revision writer must append an immutable row and never update/replace/delete one.');

// Owner identity is a keyed principal, separate from the projection digest.
for (const marker of [
  'public static function cockpit_owner_scope_digest(',
  "hash_equals( self::account_ref( $owner_user_id ), $account_ref )",
  "implode( '|', array( 'customer-trip-cockpit-owner-v1', get_current_blog_id(), $owner_user_id, $account_ref, $trip_ref ) )",
  "wp_salt( 'secure_auth' )",
]) includes('principal', marker, `Owner HMAC principal is missing ${marker}.`);
expect(count(source.principal, /hash_hmac\s*\(\s*'sha256'/g) >= 2, 'Account and cockpit owner principals must both be SHA-256 HMACs.');
for (const marker of [
  'Tra_Vel_Traveler_Principal::account_ref( $owner_user_id )',
  'Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( $owner_user_id, $account_ref, $source[\'trip_ref\'] )',
  'Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( (int) $row[\'owner_user_id\'], (string) $row[\'account_ref\'], (string) $row[\'trip_ref\'] )',
]) includes('store', marker, `Store does not derive/recheck the exact server-keyed owner binding: ${marker}.`);
for (const marker of [
  "$wpdb->last_error = '';",
  "self::error( 'read_failed'",
  "self::error( 'commit_read_failed'",
  'ORDER BY last_verified_at DESC,updated_at DESC,id DESC LIMIT %d',
  "'post_trip' !== (string) $projection['current']['phase']",
  'self::MAX_OWNER_CANDIDATES + 1',
]) includes('store', marker, `Store read reliability/current-trip selection is missing ${marker}.`);

// Plugin bootstrap, lifecycle, uninstall, REST, and public health are all wired.
for (const file of [
  'interface-tra-vel-customer-trip-cockpit-read-model-provider.php',
  'class-tra-vel-customer-trip-cockpit-store.php',
  'class-tra-vel-customer-trip-cockpit-controller.php',
]) includes('bootstrap', `require_once __DIR__ . '/${file}';`, `VIP bootstrap does not load ${file}.`);
for (const marker of [
  'Tra_Vel_Customer_Trip_Cockpit_Store::install();',
  "array( 'Tra_Vel_Customer_Trip_Cockpit_Store', 'cleanup' )",
  'Tra_Vel_Customer_Trip_Cockpit_Store::maybe_upgrade();',
  '( new Tra_Vel_Customer_Trip_Cockpit_Controller() )->register_routes();',
]) includes('plugin', marker, `Plugin lifecycle is missing ${marker}.`);
for (const marker of [
  'Tra_Vel_Customer_Trip_Cockpit_Store::schema_health()',
  'Tra_Vel_Customer_Trip_Cockpit_Store::is_ready()',
  "'expected_tables'          => 3",
  "'customer_trip_cockpit'     => $customer_cockpit_ready",
  "'customer_trip_cockpit_store' => $customer_cockpit_store_health",
]) includes('health', marker, `Agent health response is missing ${marker}.`);
for (const marker of [
  'tra_vel_customer_trip_cockpit_limits',
  'tra_vel_customer_trip_cockpit_revisions',
  'tra_vel_customer_trip_cockpits',
  "delete_option( 'tra_vel_customer_trip_cockpit_db_version' )",
  "delete_option( 'tra_vel_customer_trip_cockpit_cleanup_status' )",
  "delete_option( 'tra_vel_customer_trip_cockpit_readiness_cache' )",
]) includes('uninstall', marker, `Opt-in cleanup is missing ${marker}.`);
expect(source.uninstall.includes("defined( 'TRA_VEL_AGENT_REMOVE_DATA' )") && source.uninstall.includes('true !== TRA_VEL_AGENT_REMOVE_DATA'), 'Trip Cockpit uninstall must remain opt-in.');

// The theme sends no references, body, or browser-stored capability material.
includes('assets', "rest_url( 'tra-vel-agent/v1/customer-trip-cockpit/current' )", 'Theme must localize only the fixed current endpoint.');
includes('assets', "'customerTripCockpitUrl'", 'Theme config is missing the fixed Trip Cockpit endpoint.');
for (const rawRef of ['trip_ref', 'account_ref', 'case_ref', 'cockpit_ref', 'owner_scope_digest', 'projection_digest', 'previous_projection_digest']) {
  expect(!source.script.includes(rawRef), `Trip Cockpit browser script must not send or retain raw ${rawRef}.`);
  expect(!source.assets.includes(`'${rawRef}'`), `Theme localization must not expose raw ${rawRef}.`);
  expect(!new RegExp(`data-[^\\s>]*${rawRef.replaceAll('_', '[-_]')}(?=[\\s=>]|$)`, 'i').test(source.template), `Trip Cockpit markup must not serialize raw ${rawRef}.`);
}
for (const storageApi of ['localStorage', 'sessionStorage', 'indexedDB', 'document.cookie']) {
  expect(!source.script.includes(storageApi), `Trip Cockpit script must not persist capability/private state through ${storageApi}.`);
}
const headerLiteral = source.script.match(/const headers = \{([^;]+)\};/s)?.[1] || '';
for (const header of ["'Accept':'application/json'", "'X-Tra-Vel-Cockpit-Read':'1'", "'X-Tra-Vel-Cockpit-Mode':signedIn ? 'signed-in' : 'scoped-session'"]) {
  expect(headerLiteral.includes(header), `Browser request is missing fixed header ${header}.`);
}
const literalHeaderNames = [...headerLiteral.matchAll(/(?:^|,)\s*'([^']+)'\s*:/g)].map(match => match[1]);
expect(JSON.stringify(literalHeaderNames) === JSON.stringify(['Accept', 'X-Tra-Vel-Cockpit-Read', 'X-Tra-Vel-Cockpit-Mode']), 'Browser request base headers must remain the exact fixed three-header set.');
const dynamicHeaderWrites = [...source.script.matchAll(/headers\[['"]([^'"]+)['"]\]\s*=/g)].map(match => match[1]);
expect(dynamicHeaderWrites.length >= 1 && dynamicHeaderWrites.every(header => header === 'X-WP-Nonce'), 'Only the signed-in REST nonce may be added to browser requests.');
expect(/if \(signedIn\) headers\['X-WP-Nonce'\] = window\.traVelV2\?\.nonce \|\| '';/.test(source.script), 'REST nonce must be sent only for signed-in mode.');
includes('script', "fetch(endpoint, {method:'GET',credentials:'same-origin',cache:'no-store',referrerPolicy:'no-referrer',headers", 'Trip Cockpit browser request must remain credentialed GET with no-store/no-referrer.');
expect(!/fetch\([^\n]+\bbody\s*:/.test(source.script), 'Trip Cockpit browser request must not send a body.');
expect(!/URLSearchParams|FormData|new URL\s*\(|endpoint\s*[+`]/.test(source.script), 'Trip Cockpit browser request must not append caller-authored URL parameters.');

// The customer cockpit occupies normal document flow, never a map or viewport overlay.
const cockpitIndex = source.template.indexOf('<section class="customer-trip-cockpit"');
const commandMainIndex = source.template.indexOf('<div class="workspace-command-main">');
const followingWorkspaceIndex = source.template.indexOf('<section class="workspace-cockpit"', cockpitIndex);
expect(commandMainIndex >= 0 && cockpitIndex > commandMainIndex && followingWorkspaceIndex > cockpitIndex, 'Trip Cockpit must remain an ordinary section in the workspace command flow.');
const cockpitTag = cockpitIndex >= 0 ? source.template.slice(cockpitIndex, source.template.indexOf('\n', cockpitIndex)) : '';
expect(cockpitTag.includes('data-mode="<?php echo esc_attr( $customer_cockpit_mode ); ?>"'), 'Markup must expose only the server-selected closed display mode needed for the fixed request.');
includes('template', "$customer_cockpit_mode        = $has_customer_cockpit_session ? 'scoped-session' : ( $customer_cockpit_signed_in ? 'signed-in' : 'scoped-session' );", 'Display mode must resolve only to the scoped-session or signed-in closed modes.');
expect(!/(?:role="dialog"|aria-modal=|<dialog|popover|data-map|data-globe)/i.test(cockpitTag), 'Trip Cockpit root must not become a modal, popover, or map overlay.');
const rootStyle = source.styles.match(/\.customer-trip-cockpit\s*\{([^}]+)\}/s)?.[1] || '';
expect(/position:\s*static\s*;/.test(rootStyle), 'Trip Cockpit root must explicitly remain in normal document flow.');
expect(!/position:\s*(?:fixed|absolute|sticky)\s*;/.test(rootStyle), 'Trip Cockpit root must not use fixed, absolute, or sticky positioning.');

if (failures.length) {
  console.error('Customer Trip Cockpit bridge contract failed:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('Customer Trip Cockpit bridge contract passed (1 read-only current route; signed nonce or exact capability cookie; 3 InnoDB tables; immutable revisions; HMAC owner binding; minimized normal-flow UI request).');
