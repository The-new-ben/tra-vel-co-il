import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const themeRoot = join(repoRoot, 'theme', 'tra-vel-v2');
const failures = [];

const requiredFiles = [
  'style.css',
  'theme.json',
  'functions.php',
  'header.php',
  'footer.php',
  'index.php',
  'front-page.php',
  'page-map.php',
  'page-destination.php',
  'page-experience.php',
  'inc/setup.php',
  'inc/assets.php',
  'inc/discovery.php',
  'inc/suppliers/bootstrap.php',
  'inc/suppliers/interface-supplier-adapter.php',
  'inc/suppliers/class-demo-supplier-adapter.php',
  'inc/suppliers/class-open-meteo-supplier-adapter.php',
  'inc/suppliers/class-supplier-registry.php',
  'inc/suppliers/class-discovery-repository.php',
  'inc/template-tags.php',
  'inc/seo.php',
  'assets/css/app.css',
  'assets/js/app.js',
  'assets/data/discovery-demo.json',
  'assets/data/discovery.schema.json',
  'assets/vendor/lucide.min.js',
  'assets/images/earth-blue-marble.jpg',
  'assets/images/thailand.jpg'
];

for (const file of requiredFiles) {
  if (!existsSync(join(themeRoot, file))) failures.push(`Missing required theme file: ${file}`);
}

const style = readFileSync(join(themeRoot, 'style.css'), 'utf8');
for (const header of ['Theme Name: Tra-Vel V2', 'Version:', 'Requires at least:', 'Requires PHP:', 'Text Domain: tra-vel-v2']) {
  if (!style.includes(header)) failures.push(`style.css is missing header: ${header}`);
}

try {
  const themeJson = JSON.parse(readFileSync(join(themeRoot, 'theme.json'), 'utf8'));
  if (themeJson.version !== 3) failures.push('theme.json must use schema version 3.');
  const palette = themeJson?.settings?.color?.palette || [];
  const slugs = palette.map((item) => item.slug);
  if (new Set(slugs).size !== slugs.length) failures.push('theme.json contains duplicate color slugs.');
} catch (error) {
  failures.push(`theme.json is invalid JSON: ${error.message}`);
}

function walk(directory) {
  return readdirSync(directory).flatMap((name) => {
    const path = join(directory, name);
    return statSync(path).isDirectory() ? walk(path) : [path];
  });
}

const allFiles = walk(themeRoot);
const forbidden = allFiles.filter((path) => /(?:^|[\\/])(?:\.git|node_modules|dist|output)(?:[\\/]|$)/.test(path));
for (const path of forbidden) failures.push(`Forbidden packaged path: ${relative(themeRoot, path)}`);

for (const path of allFiles.filter((file) => file.endsWith('.php'))) {
  const source = readFileSync(path, 'utf8');
  if (/\b(?:var_dump|print_r)\s*\(/.test(source)) failures.push(`Debug output found in ${relative(themeRoot, path)}`);
  if (!source.includes('<?php')) failures.push(`PHP opening tag missing in ${relative(themeRoot, path)}`);
}

const appCss = readFileSync(join(themeRoot, 'assets/css/app.css'), 'utf8');
if (!appCss.includes('direction: rtl')) failures.push('RTL direction is missing from the production stylesheet.');
if (!appCss.includes("../images/earth-blue-marble.jpg")) failures.push('The production globe image path is not theme-relative.');

const appJs = readFileSync(join(themeRoot, 'assets/js/app.js'), 'utf8');
if (!appJs.includes('window.traVelV2')) failures.push('The app script is not connected to localized WordPress configuration.');
if (!appJs.includes('discoveryUrl')) failures.push('The app script is not connected to the discovery REST contract.');
if (!appJs.includes('hydrateDiscovery')) failures.push('The app script does not hydrate the map from discovery data.');

const discoveryController = readFileSync(join(themeRoot, 'inc/discovery.php'), 'utf8');
if (/function\s+get_items\s*\(\s*WP_REST_Request\b/.test(discoveryController)) {
  failures.push('REST controller overrides must keep the untyped WP_REST_Controller method signature for PHP 8 compatibility.');
}
if (!discoveryController.includes("'/' . $this->rest_base . '/cache'")) failures.push('The discovery cache administration route is missing.');
if (!discoveryController.includes("current_user_can( 'manage_options' )")) failures.push('Discovery cache mutation lacks a manage_options capability check.');

const supplierInterface = readFileSync(join(themeRoot, 'inc/suppliers/interface-supplier-adapter.php'), 'utf8');
for (const method of ['get_id', 'get_verticals', 'is_configured', 'get_mode', 'get_cache_version', 'fetch']) {
  if (!supplierInterface.includes(`function ${method}(`)) failures.push(`Supplier adapter contract is missing ${method}().`);
}

const frontPage = readFileSync(join(themeRoot, 'front-page.php'), 'utf8');
if (/<a\b[^>]*class="ai-input"[^>]*>[\s\S]*?<button\b/.test(frontPage)) {
  failures.push('The AI planner link contains an invalid nested button.');
}

if (failures.length) {
  console.error('Tra-Vel V2 theme validation failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel V2 theme validation passed (${allFiles.length} files).`);
