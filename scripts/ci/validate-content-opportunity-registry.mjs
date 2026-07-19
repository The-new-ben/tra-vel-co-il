import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const registryPath = join(repoRoot, 'content', 'seo', 'content-opportunity-registry.json');
const discoveryPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'discovery-demo.json');
const guideDir = join(repoRoot, 'content', 'guides');
const failures = [];
const projectToday = (() => {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Jerusalem',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).formatToParts(new Date());
  const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
  return `${values.year}-${values.month}-${values.day}`;
})();

function fail(message) {
  failures.push(message);
}

function readJson(path, label) {
  if (!existsSync(path)) {
    fail(`${label} is missing.`);
    return {};
  }
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    fail(`${label} is invalid JSON: ${error.message}`);
    return {};
  }
}

function destinationSupportContractErrors(entry, parent) {
  const errors = [];
  if (!/^\/destinations\/[a-z0-9-]+\/[a-z0-9-]+\/$/.test(entry?.canonicalPath || '')) {
    errors.push('destination-support canonicalPath must be exactly one segment below its destination hub.');
  }
  const immediateParentPath = String(entry?.canonicalPath || '').replace(/[^/]+\/$/, '');
  if (entry?.parentPath !== immediateParentPath) {
    errors.push(`parentPath must be the immediate destination-hub path ${immediateParentPath || '(invalid path)'}.`);
  }
  if (parent && parent.pageType !== 'destination-hub') errors.push('destination-support parent must be a destination-hub.');
  if (parent && parent.cluster !== entry?.cluster) errors.push('destination-support cluster must match its destination-hub parent.');
  if (entry?.status === 'content-ready' && parent && !['content-ready', 'live'].includes(parent.status)) {
    errors.push('a content-ready destination-support page requires a content-ready or live destination-hub parent.');
  }
  return errors;
}

const sourcePacketPageTypes = new Set(['destination-hub', 'destination-support', 'decision-guide']);
const publicRegistryStatuses = new Set(['content-ready', 'live']);

function decisionGuideContractErrors(entry, parent) {
  const errors = [];
  const match = String(entry?.canonicalPath || '').match(/^\/guides\/([a-z0-9-]+)\/[a-z0-9-]+\/$/);
  if (!match) {
    errors.push('decision-guide canonicalPath must be /guides/{cluster}/{guide}/.');
    return errors;
  }
  const semanticParentPath = `/destinations/${match[1]}/`;
  if (entry?.cluster !== match[1]) errors.push('decision-guide cluster must match its /guides/{cluster}/ path segment.');
  if (entry?.parentPath !== semanticParentPath) errors.push(`decision-guide parentPath must be its semantic destination hub ${semanticParentPath}.`);
  if (parent && parent.pageType !== 'destination-hub') errors.push('decision-guide semantic parent must be a destination-hub.');
  if (parent && parent.cluster !== entry?.cluster) errors.push('decision-guide cluster must match its semantic destination-hub parent.');
  if (publicRegistryStatuses.has(entry?.status) && parent && !publicRegistryStatuses.has(parent.status)) {
    errors.push('a public decision-guide requires a content-ready or live semantic destination-hub parent.');
  }
  return errors;
}

function publicationPacketBindingErrors(entry, packet) {
  const errors = [];
  if (!entry) return ['publish-ready guide packet has no exact content registry owner.'];

  const isPublicOwner = publicRegistryStatuses.has(entry.status);
  if (sourcePacketPageTypes.has(entry.pageType)) {
    if (isPublicOwner) {
      if (!packet) {
        errors.push(`${entry.pageType} owner with status ${entry.status} requires an exact publish-ready guide packet.`);
      } else {
        if (packet.status !== 'publish-ready') errors.push(`${entry.pageType} owner with status ${entry.status} requires packet status publish-ready.`);
        if (packet.canonicalPath !== entry.canonicalPath) errors.push('guide packet canonicalPath does not exactly match its registry owner.');
        if (packet.mapState !== entry.mapState) errors.push('registry owner and guide packet mapState differ.');
      }
    } else if (packet?.status === 'publish-ready') {
      errors.push(`publish-ready guide packet requires a content-ready or live ${entry.pageType} registry owner.`);
    }
  } else if (entry.pageType === 'transactional-cluster' && packet?.status === 'publish-ready') {
    errors.push('transactional-cluster owners must use the dedicated transactional publication gate, not a publish-ready guide packet.');
  }
  return errors;
}

const registry = readJson(registryPath, 'Content opportunity registry');
const discovery = readJson(discoveryPath, 'Discovery dataset');
const knownMapStates = new Set((discovery.destinations || []).map(destination => destination.id));
const guidePackets = new Map();

if (existsSync(guideDir)) {
  for (const file of readdirSync(guideDir).filter(name => name.endsWith('.sources.json'))) {
    const packet = readJson(join(guideDir, file), `Guide packet ${file}`);
    if (packet.canonicalPath) guidePackets.set(packet.canonicalPath, packet);
  }
}

if (registry.schemaVersion !== 1) fail('Registry schemaVersion must be 1.');
if (registry.locale !== 'he-IL') fail('Registry locale must be he-IL.');
if (!/^\d{4}-\d{2}-\d{2}$/.test(registry.updated || '')) fail('Registry updated must be an ISO date.');
if (/^\d{4}-\d{2}-\d{2}$/.test(registry.updated || '') && registry.updated > projectToday) {
  fail('Registry updated cannot be in the future in the project timezone.');
}
if (typeof registry.evidenceBoundary !== 'string' || !/not search-volume claims/i.test(registry.evidenceBoundary)) {
  fail('Registry must explicitly state that priorities are not search-volume claims.');
}
if (!Array.isArray(registry.entries) || registry.entries.length < 30) fail('Registry must contain at least 30 prioritized entries.');

const entries = Array.isArray(registry.entries) ? registry.entries : [];
const ids = new Set();
const paths = new Set();
const entriesByPath = new Map();
const intents = new Set();
const allowedPageTypes = new Set(['commercial-hub', 'planning-tool', 'audience-hub', 'destination-hub', 'destination-support', 'transactional-cluster', 'decision-guide']);
const allowedStatuses = new Set(['live', 'content-ready', 'backlog']);

for (const [index, entry] of entries.entries()) {
  const label = entry?.id || `entry ${index + 1}`;
  if (!entry || typeof entry !== 'object' || Array.isArray(entry)) {
    fail(`Entry ${index + 1} must be an object.`);
    continue;
  }
  const allowedKeys = new Set(['id', 'canonicalPath', 'pageType', 'primaryIntent', 'cluster', 'parentPath', 'mapState', 'status', 'conversionAction', 'monetization']);
  for (const key of Object.keys(entry)) if (!allowedKeys.has(key)) fail(`${label}: unexpected field ${key}.`);
  for (const key of allowedKeys) if (!(key in entry)) fail(`${label}: missing ${key}.`);

  if (!/^[a-z0-9-]+$/.test(entry.id || '')) fail(`${label}: id must be a lowercase slug.`);
  if (ids.has(entry.id)) fail(`${label}: duplicate id.`);
  ids.add(entry.id);

  if (!/^\/(?:[a-z0-9-]+\/)*$/.test(entry.canonicalPath || '')) fail(`${label}: canonicalPath must be a clean trailing-slash path.`);
  if (paths.has(entry.canonicalPath)) fail(`${label}: duplicate canonicalPath ${entry.canonicalPath}.`);
  paths.add(entry.canonicalPath);
  if (typeof entry.canonicalPath === 'string' && !entriesByPath.has(entry.canonicalPath)) {
    entriesByPath.set(entry.canonicalPath, entry);
  }

  if (!allowedPageTypes.has(entry.pageType)) fail(`${label}: unsupported pageType ${entry.pageType}.`);
  if (!allowedStatuses.has(entry.status)) fail(`${label}: unsupported status ${entry.status}.`);
  if (typeof entry.primaryIntent !== 'string' || entry.primaryIntent.trim().length < 8 || !/[\u0590-\u05ff]/u.test(entry.primaryIntent)) fail(`${label}: primaryIntent must be a useful Hebrew phrase.`);
  const normalizedIntent = String(entry.primaryIntent || '').trim().toLowerCase();
  if (intents.has(normalizedIntent)) fail(`${label}: primaryIntent duplicates another page owner.`);
  intents.add(normalizedIntent);
  if (typeof entry.cluster !== 'string' || !/^[a-z0-9-]+$/.test(entry.cluster)) fail(`${label}: cluster must be a lowercase slug.`);
  if (typeof entry.parentPath !== 'string' || !/^\/(?:[a-z0-9-]+\/)*$/.test(entry.parentPath)) fail(`${label}: parentPath is invalid.`);
  if (!(entry.mapState === null || (typeof entry.mapState === 'string' && knownMapStates.has(entry.mapState)))) fail(`${label}: mapState is not present in discovery data.`);
  if (typeof entry.conversionAction !== 'string' || entry.conversionAction.trim().length < 12 || !/[\u0590-\u05ff]/u.test(entry.conversionAction)) fail(`${label}: conversionAction must be traveler-facing Hebrew copy.`);
  if (!Array.isArray(entry.monetization) || !entry.monetization.length || entry.monetization.some(value => !/^[a-z0-9-]+$/.test(value))) fail(`${label}: monetization must contain product slugs.`);
  if (new Set(entry.monetization || []).size !== (entry.monetization || []).length) fail(`${label}: monetization contains duplicates.`);
  if (/[—–]/u.test(`${entry.primaryIntent || ''}${entry.conversionAction || ''}`)) fail(`${label}: public copy must not use em dash or en dash punctuation.`);

  if (entry.pageType === 'transactional-cluster' && !/^\/(?:flights|hotels|packages)\/[a-z0-9-]+\/$/.test(entry.canonicalPath || '')) {
    fail(`${label}: transactional clusters must live below flights, hotels or packages.`);
  }
}

for (const entry of entries) {
  const parent = entry.parentPath === '/' ? null : entriesByPath.get(entry.parentPath);
  if (entry.parentPath !== '/' && !parent) fail(`${entry.id}: parentPath ${entry.parentPath} has no registry owner.`);

  if (entry.pageType === 'destination-support') {
    for (const message of destinationSupportContractErrors(entry, parent)) fail(`${entry.id}: ${message}`);
  }
  if (entry.pageType === 'decision-guide') {
    for (const message of decisionGuideContractErrors(entry, parent)) fail(`${entry.id}: ${message}`);
  }
  for (const message of publicationPacketBindingErrors(entry, guidePackets.get(entry.canonicalPath))) {
    fail(`${entry.id}: ${message}`);
  }
}

const destinationSupportFixture = {
  canonicalPath: '/destinations/thailand/bangkok/', parentPath: '/destinations/thailand/',
  pageType: 'destination-support', cluster: 'thailand', status: 'content-ready'
};
const destinationHubFixture = { pageType: 'destination-hub', cluster: 'thailand', status: 'content-ready' };
if (destinationSupportContractErrors(destinationSupportFixture, destinationHubFixture).length) {
  fail('Destination-support validator rejects a valid nested content-ready owner.');
}
if (!destinationSupportContractErrors(destinationSupportFixture, { ...destinationHubFixture, status: 'backlog' }).length) {
  fail('Destination-support validator accepts a content-ready child under a backlog parent.');
}
if (!destinationSupportContractErrors({ ...destinationSupportFixture, parentPath: '/destinations/athens/' }, destinationHubFixture).length) {
  fail('Destination-support validator accepts a non-immediate parentPath.');
}

const decisionParentFixture = {
  id: 'vienna-guide', canonicalPath: '/destinations/vienna/', pageType: 'destination-hub',
  cluster: 'vienna', status: 'content-ready', mapState: 'vienna'
};
const decisionOwnerFixture = {
  id: 'vienna-areas', canonicalPath: '/guides/vienna/where-to-stay/', pageType: 'decision-guide',
  cluster: 'vienna', parentPath: '/destinations/vienna/', status: 'content-ready', mapState: 'vienna'
};
const decisionPacketFixture = {
  id: 'vienna-areas-2026', canonicalPath: '/guides/vienna/where-to-stay/', status: 'publish-ready', mapState: 'vienna'
};
if (decisionGuideContractErrors(decisionOwnerFixture, decisionParentFixture).length || publicationPacketBindingErrors(decisionOwnerFixture, decisionPacketFixture).length) {
  fail('Decision-guide publication validator rejects a valid exact owner, semantic parent, and publish-ready packet.');
}
if (!publicationPacketBindingErrors({ ...decisionOwnerFixture, status: 'live' }, null).length) {
  fail('Decision-guide publication validator accepts a live owner without a publish-ready packet.');
}
const transactionalOwnerFixture = {
  id: 'vienna-flights', canonicalPath: '/flights/vienna/', pageType: 'transactional-cluster',
  cluster: 'vienna', parentPath: '/flights/', status: 'content-ready', mapState: 'vienna'
};
if (!publicationPacketBindingErrors(transactionalOwnerFixture, { ...decisionPacketFixture, canonicalPath: '/flights/vienna/' }).length) {
  fail('Transactional publication validator accepts a publish-ready guide packet instead of its dedicated runtime gate.');
}
const destinationPacketFixture = {
  id: 'athens-2026', canonicalPath: '/destinations/athens/', status: 'publish-ready', mapState: 'athens'
};
const destinationOwnerFixture = {
  id: 'athens-guide', canonicalPath: '/destinations/athens/', pageType: 'destination-hub',
  cluster: 'athens', parentPath: '/destinations/', status: 'content-ready', mapState: 'athens'
};
if (publicationPacketBindingErrors(destinationOwnerFixture, destinationPacketFixture).length) {
  fail('Destination publication validator rejects the existing content-ready owner and publish-ready packet contract.');
}
if (!publicationPacketBindingErrors({ ...destinationOwnerFixture, status: 'live' }, null).length) {
  fail('Destination publication validator accepts a live destination without a publish-ready packet.');
}

for (const [canonicalPath, packet] of guidePackets) {
  if (packet.status !== 'publish-ready') continue;
  const owner = entriesByPath.get(canonicalPath);
  if (!owner) fail(`${packet.id}: publish-ready guide packet has no exact content registry owner.`);
}

if (failures.length) {
  console.error('Tra-Vel content opportunity registry validation failed:');
  failures.forEach(message => console.error(`- ${message}`));
  process.exit(1);
}

console.log(`Tra-Vel content opportunity registry validation passed (${entries.length} owned intents).`);
