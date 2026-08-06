import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

// Premium planet contract (theme 1.43.0): the Cesium + Google Photorealistic
// 3D Tiles upgrade must stay a lazily activated, server-granted, fail-closed
// layer behind the legacy globe. This validator proves the key boundary, the
// grant budget, the control gating, the attribution surface, the vendored
// license, and the scroll law scope, and it scans the entire committed tree
// for anything shaped like a Google browser key.

const repoRoot = fileURLToPath(new URL('../..', import.meta.url));
const themeRoot = join(repoRoot, 'theme', 'tra-vel-v2');
const failures = [];

function read(path, label) {
  if (!existsSync(path)) {
    failures.push(`${label} is missing: ${relative(repoRoot, path)}`);
    return '';
  }
  return readFileSync(path, 'utf8');
}

const planetPhp = read(join(themeRoot, 'inc', 'planet.php'), 'Premium planet server module');
const assetsPhp = read(join(themeRoot, 'inc', 'assets.php'), 'Theme asset loader');
const functionsPhp = read(join(themeRoot, 'functions.php'), 'Theme bootstrap');
const premiumJs = read(join(themeRoot, 'assets', 'js', 'planet-premium.js'), 'Premium planet client');
const globeJs = read(join(themeRoot, 'assets', 'js', 'globe-3d.js'), 'Legacy globe');
const appCss = read(join(themeRoot, 'assets', 'css', 'app.css'), 'Theme stylesheet');

// The key shape is assembled at runtime so this validator's own source can
// never trip the committed-tree scan below.
const keyShape = 'AIza' + '[0-9A-Za-z_-]{35}';

// ---------------------------------------------------------------------------
// 1. Grant endpoint contract: option gating, validated key shape, date-keyed
//    daily counter against the cap option, fail-closed 403s, uncacheable
//    response, and the REST nonce requirement.
// ---------------------------------------------------------------------------
for (const [marker, reason] of [
  ["register_rest_route(", 'the grant endpoint registration'],
  ["'/planet/upgrade-grant'", 'the grant route path'],
  ['WP_REST_Server::CREATABLE', 'the POST-only method contract'],
  ["wp_verify_nonce( $nonce, 'wp_rest' )", 'the REST nonce requirement'],
  ["get_option( 'tra_vel_v2_gmp_key', '' )", 'the single key option read'],
  [`preg_match( '/^${keyShape}$/', $` + 'key )', 'the key shape validation'],
  ["get_option( 'tra_vel_v2_gmp_daily_cap', 25 )", 'the daily cap option with its 25-grant default'],
  ["current_time( 'Y-m-d' )", 'the date-keyed counter day'],
  ["get_option( 'tra_vel_v2_gmp_daily_counter', array() )", 'the daily counter option'],
  ['$today === $counter[\'date\']', 'the counter date scoping'],
  ['$cap <= 0 || $count >= $cap', 'the fail-closed budget comparison'],
  ["new WP_Error( 'tra_vel_planet_unavailable'", 'the no-key refusal'],
  ["new WP_Error( 'tra_vel_planet_cap_reached'", 'the cap refusal'],
  ["array( 'status' => 403 )", 'the 403 refusal status'],
  ["header( 'Cache-Control', 'no-store' )", 'the uncacheable grant response'],
  ["update_option( 'tra_vel_v2_gmp_daily_counter', array( 'date' => $today, 'count' => $count ), false )", 'the non-autoloaded counter write'],
]) {
  if (!planetPhp.includes(marker)) failures.push(`inc/planet.php is missing ${reason}.`);
}
if ((planetPhp.match(/array\( 'status' => 403 \)/g) || []).length < 2) {
  failures.push('Both grant refusals must carry an explicit 403 status.');
}

// ---------------------------------------------------------------------------
// 2. The key never reaches markup, localized data, logs, or client storage.
// ---------------------------------------------------------------------------
function walk(directory) {
  return readdirSync(directory).flatMap((name) => {
    const path = join(directory, name);
    return statSync(path).isDirectory() ? walk(path) : [path];
  });
}
const themeFiles = walk(themeRoot);
for (const path of themeFiles) {
  if (!/\.(?:php|js|json|css)$/.test(path)) continue;
  if (path.endsWith(join('inc', 'planet.php'))) continue;
  if (readFileSync(path, 'utf8').includes('tra_vel_v2_gmp_key')) {
    failures.push(`The key option name may live only in inc/planet.php, not ${relative(themeRoot, path)}.`);
  }
}
if (planetPhp.includes('wp_localize_script')) failures.push('inc/planet.php must never localize script data.');
if (/error_log|trigger_error/.test(planetPhp)) failures.push('inc/planet.php must never log; the key could reach the log stream.');
const localizeBlock = assetsPhp.slice(assetsPhp.indexOf("'traVelV2Planet'"), assetsPhp.indexOf("'traVelV2Planet'") + 700);
if (!localizeBlock.includes("'grantUrl'") || !localizeBlock.includes("'nonce'") || !localizeBlock.includes("'vendorBase'")) {
  failures.push('The premium loader must be localized with its grant endpoint, nonce, and vendor base.');
}
if (/'key'|gmp_key|planet_gmp_key/.test(localizeBlock)) failures.push('The localized premium data must never carry the key.');
for (const banned of ['localStorage', 'sessionStorage', 'document.cookie', 'console.log(']) {
  if (premiumJs.includes(banned)) failures.push(`planet-premium.js must not use ${banned}; the granted key stays in one closure.`);
}

// ---------------------------------------------------------------------------
// 3. Server-gated control, lazy activation, and the no-auto-activation law.
// ---------------------------------------------------------------------------
if (!/function tra_vel_v2_premium_planet_control\(\) \{\s*\n\tif \( ! tra_vel_v2_planet_upgrade_available\(\) \) \{\s*\n\t\treturn;/.test(planetPhp)) {
  failures.push('The upgrade control must render nothing at all when no key option is set.');
}
if (!planetPhp.includes('כדור הארץ האמיתי')) failures.push('The upgrade control lost its Hebrew label.');
if (!/data-planet-upgrade type="button" hidden/.test(planetPhp)) {
  failures.push('The upgrade control must ship hidden until the client guards reveal it.');
}
for (const template of ['front-page.php', 'page-map.php', 'page-pillar.php', 'page-destination.php', 'page-seo-opportunity.php']) {
  if (!read(join(themeRoot, template), template).includes('tra_vel_v2_premium_planet_control()')) {
    failures.push(`${template} does not render the premium planet control.`);
  }
}
if (!functionsPhp.includes("require_once TRA_VEL_V2_PATH . '/inc/planet.php';")) failures.push('functions.php does not load the planet module.');
if (!/if \( function_exists\( 'tra_vel_v2_planet_upgrade_available' \) && tra_vel_v2_planet_upgrade_available\(\) \) \{[\s\S]{0,400}planet-premium\.js/.test(assetsPhp)) {
  failures.push('planet-premium.js must enqueue only while the server can actually grant a session.');
}
if (!assetsPhp.includes("'tra-vel-v2-planet-premium'") || !/'tra-vel-v2-planet-premium',[\s\S]{0,700}defer src=/.test(assetsPhp)) {
  failures.push('The premium loader must join the deferred script set.');
}
if (!/\.globe-premium-upgrade \{[^}]*min-width: 44px; min-height: 44px;/.test(appCss)) {
  failures.push('The upgrade control must keep a 44px touch target.');
}
if ((premiumJs.match(/^\s*activate\(\);$/gm) || []).length !== 1 || !/control\.addEventListener\('click', function \(event\) \{\s*event\.stopPropagation\(\);\s*activate\(\);/.test(premiumJs)) {
  failures.push('Activation must run exactly once, from the explicit control tap, never automatically.');
}
if ((premiumJs.match(/document\.createElement\('script'\)/g) || []).length !== 1) {
  failures.push('The vendor script may be injected in exactly one place, inside the guarded loader.');
}
if (premiumJs.indexOf('requestGrant()') < 0 || premiumJs.indexOf('return loadCesium') < premiumJs.indexOf('requestGrant().then')) {
  failures.push('Cesium may load only after a grant succeeds.');
}
for (const guard of ['webgl2Available', 'saveData === true', 'permanentDenial', 'restoreLegacy', "classList.contains('is-webgl-ready')", "classList.contains('globe-3d-unavailable')"]) {
  if (!premiumJs.includes(guard)) failures.push(`planet-premium.js lost its fail-closed guard: ${guard}.`);
}

// ---------------------------------------------------------------------------
// 4. Attribution stays visible: screen credits on, a real credit container,
//    and no rule that hides either surface.
// ---------------------------------------------------------------------------
if (!premiumJs.includes('showCreditsOnScreen: true')) failures.push('The Google tileset must render its data attributions on screen.');
if (!premiumJs.includes('creditContainer: credits')) failures.push('The premium widget must mount its credits into the visible strip.');
if (!appCss.includes('.globe-premium-credits {')) failures.push('The credit strip styling is missing.');
for (const rule of appCss.match(/[^{}]*\{[^}]*\}/g) || []) {
  if (/(?:globe-premium-credits|cesium-credit)/.test(rule) && /display:\s*none|visibility:\s*hidden/.test(rule)) {
    failures.push('Attribution surfaces must never be hidden by the stylesheet.');
  }
}

// ---------------------------------------------------------------------------
// 5. Vendored Cesium: present, licensed, untouched shape.
// ---------------------------------------------------------------------------
const vendorRoot = join(themeRoot, 'assets', 'vendor', 'cesium');
for (const required of ['LICENSE.md', 'Cesium.js', join('Widgets', 'widgets.css'), join('Workers', 'decodeDraco.js'), join('ThirdParty', 'draco_decoder.wasm')]) {
  if (!existsSync(join(vendorRoot, required))) failures.push(`Vendored Cesium is missing ${required}.`);
}
const license = read(join(vendorRoot, 'LICENSE.md'), 'Cesium license');
if (!license.includes('Apache License')) failures.push('The vendored Cesium license must remain the Apache License text.');

// ---------------------------------------------------------------------------
// 6. Scroll law, scope-adjusted: our own theme scripts never bind wheel,
//    mousewheel, or touchmove listeners anywhere, and bind scroll only as the
//    one passive window-level intent observer app.js already carries. The
//    assets/vendor/** tree alone is exempt: the vendored Cesium runtime owns
//    wheel and touch gestures inside its own canvas, which the release
//    explicitly allows, while every first-party file stays banned.
// ---------------------------------------------------------------------------
const listenerPattern = /addEventListener\(\s*['"](wheel|mousewheel|scroll|touchmove)['"]/g;
for (const path of themeFiles) {
  if (!path.endsWith('.js')) continue;
  if (path.includes(join('assets', 'vendor') + '\\') || path.includes(join('assets', 'vendor') + '/')) continue;
  const source = readFileSync(path, 'utf8');
  for (const match of source.matchAll(listenerPattern)) {
    const kind = match[1];
    if (kind === 'scroll' && path.endsWith('app.js')) continue;
    failures.push(`${relative(themeRoot, path)} binds a ${kind} listener; page scrolling stays with the browser.`);
  }
}
const appJsSource = read(join(themeRoot, 'assets', 'js', 'app.js'), 'app.js');
const appScrollBindings = [...appJsSource.matchAll(/window\.addEventListener\('scroll'/g)];
if (appScrollBindings.length !== 1 || !appJsSource.slice(appScrollBindings[0].index, appScrollBindings[0].index + 300).includes('{ passive: true }')) {
  failures.push('app.js may keep exactly one passive window scroll observer and nothing more.');
}

// ---------------------------------------------------------------------------
// 7. The legacy globe handoff: the frame loop stands down under the premium
//    class and the camera snapshot exists for view continuity.
// ---------------------------------------------------------------------------
if (!globeJs.includes("root.classList.contains('is-premium-planet-active')) return;")) {
  failures.push('The legacy frame loop must stand down while the premium planet owns the panel.');
}
if (!globeJs.includes('function cameraState()') || !globeJs.includes('cameraState(targetRoot = null)')) {
  failures.push('The legacy globe must expose its read-only camera snapshot for the premium handoff.');
}
if (!premiumJs.includes('cameraState(root)')) failures.push('The premium camera must open from the legacy view snapshot.');

// ---------------------------------------------------------------------------
// 8. No key anywhere in the committed tree.
// ---------------------------------------------------------------------------
const keyScan = new RegExp(keyShape);
const tracked = execFileSync('git', ['-C', repoRoot, 'ls-files', '-z'], { maxBuffer: 1024 * 1024 * 64 })
  .toString('utf8')
  .split('\u0000')
  .filter(Boolean);
for (const name of tracked) {
  const path = join(repoRoot, name);
  if (!existsSync(path)) continue;
  if (keyScan.test(readFileSync(path, 'latin1'))) {
    failures.push(`Committed file carries a Google-key-shaped string: ${name}`);
  }
}

if (failures.length) {
  console.error('Premium planet validation failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Premium planet validation passed (${tracked.length} committed files key-scanned; vendor exemption scoped to assets/vendor/** only).`);
