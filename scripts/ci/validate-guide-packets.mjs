import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const packetDir = join(repoRoot, 'content', 'guides');
const schemaPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'guide-source-packet.schema.json');
const failures = [];
const seenTopics = new Map();
const seenPaths = new Map();

function isIsoDate(value) {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(`${value}T00:00:00Z`));
}

function fail(file, message) {
  failures.push(`${file}: ${message}`);
}

if (!existsSync(schemaPath)) failures.push('Guide packet JSON schema is missing.');
else {
  try { JSON.parse(readFileSync(schemaPath, 'utf8')); }
  catch (error) { failures.push(`Guide packet JSON schema is invalid: ${error.message}`); }
}

if (!existsSync(packetDir)) failures.push('content/guides is missing.');
const packetFiles = existsSync(packetDir)
  ? readdirSync(packetDir).filter((name) => name.endsWith('.sources.json')).sort()
  : [];
if (!packetFiles.length) failures.push('At least one destination source packet is required.');

for (const file of packetFiles) {
  let packet;
  try { packet = JSON.parse(readFileSync(join(packetDir, file), 'utf8')); }
  catch (error) { fail(file, `invalid JSON: ${error.message}`); continue; }

  for (const key of ['schemaVersion', 'id', 'locale', 'primaryTopic', 'canonicalPath', 'status', 'wordTargetMin', 'checkedAt', 'mapState', 'sections', 'sources', 'facts']) {
    if (!(key in packet)) fail(file, `missing ${key}`);
  }
  if (packet.schemaVersion !== 1) fail(file, 'schemaVersion must be 1.');
  if (packet.locale !== 'he-IL') fail(file, 'locale must be he-IL.');
  if (!/^[a-z0-9-]+$/.test(packet.id || '')) fail(file, 'id must be a lowercase slug.');
  if (!/^\/[a-z0-9-]+\/$/.test(packet.canonicalPath || '')) fail(file, 'canonicalPath must be one clean, trailing-slash route.');
  if (!['research', 'source-ready', 'editorial-review', 'publish-ready'].includes(packet.status)) fail(file, 'status is invalid.');
  if (!Number.isInteger(packet.wordTargetMin) || packet.wordTargetMin < 5000) fail(file, 'wordTargetMin must be at least 5000.');
  if (!isIsoDate(packet.checkedAt)) fail(file, 'checkedAt must be a valid ISO date.');
  if (isIsoDate(packet.checkedAt) && Date.parse(`${packet.checkedAt}T00:00:00Z`) > Date.now()) fail(file, 'checkedAt cannot be in the future.');
  if (!Array.isArray(packet.sections) || packet.sections.length < 12) fail(file, 'at least 12 decision-oriented sections are required.');
  if (!Array.isArray(packet.sources) || packet.sources.length < 10) fail(file, 'at least 10 sources are required.');
  if (!Array.isArray(packet.facts) || packet.facts.length < 10) fail(file, 'at least 10 source-mapped facts are required.');

  const topicKey = String(packet.primaryTopic || '').trim().toLowerCase();
  if (seenTopics.has(topicKey)) fail(file, `primary topic duplicates ${seenTopics.get(topicKey)}.`);
  else seenTopics.set(topicKey, file);
  if (seenPaths.has(packet.canonicalPath)) fail(file, `canonical path duplicates ${seenPaths.get(packet.canonicalPath)}.`);
  else seenPaths.set(packet.canonicalPath, file);

  const sourceIds = new Set();
  const sourceUrls = new Set();
  let officialCount = 0;
  for (const source of packet.sources || []) {
    if (!source || typeof source !== 'object') { fail(file, 'every source must be an object.'); continue; }
    if (!/^[a-z0-9-]+$/.test(source.id || '')) fail(file, 'every source needs a lowercase slug id.');
    if (sourceIds.has(source.id)) fail(file, `duplicate source id ${source.id}.`);
    sourceIds.add(source.id);
    if (!/^https:\/\//.test(source.url || '')) fail(file, `${source.id || 'source'} must use HTTPS.`);
    if (sourceUrls.has(source.url)) fail(file, `duplicate source URL ${source.url}.`);
    sourceUrls.add(source.url);
    if (!isIsoDate(source.checkedAt)) fail(file, `${source.id || 'source'} has an invalid checkedAt date.`);
    if (!Array.isArray(source.supports) || !source.supports.length) fail(file, `${source.id || 'source'} must state what it supports.`);
    if (source.type === 'official') officialCount += 1;
  }
  if (officialCount < 6) fail(file, 'at least six official/first-party sources are required.');

  const factIds = new Set();
  for (const fact of packet.facts || []) {
    if (!fact || typeof fact !== 'object') { fail(file, 'every fact must be an object.'); continue; }
    if (!/^[a-z0-9-]+$/.test(fact.id || '')) fail(file, 'every fact needs a lowercase slug id.');
    if (factIds.has(fact.id)) fail(file, `duplicate fact id ${fact.id}.`);
    factIds.add(fact.id);
    if (!Array.isArray(fact.sourceIds) || !fact.sourceIds.length) fail(file, `${fact.id || 'fact'} has no source mapping.`);
    for (const sourceId of fact.sourceIds || []) {
      if (!sourceIds.has(sourceId)) fail(file, `${fact.id || 'fact'} references unknown source ${sourceId}.`);
    }
    if (fact.volatile === true && fact.recheckBeforePublish !== true) fail(file, `${fact.id || 'fact'} is volatile but lacks the publish-time recheck gate.`);
  }

  if (packet.status === 'publish-ready') {
    if (!packet.contentPath) fail(file, 'publish-ready packets require contentPath.');
    else {
      const contentPath = resolve(repoRoot, packet.contentPath);
      if (!existsSync(contentPath)) fail(file, `publish-ready content is missing: ${packet.contentPath}.`);
      else {
        const words = readFileSync(contentPath, 'utf8').match(/[\p{L}\p{N}][\p{L}\p{N}\u05be'’-]*/gu) || [];
        if (words.length < packet.wordTargetMin) fail(file, `content has ${words.length} words; ${packet.wordTargetMin} required.`);
      }
    }
  }
}

if (failures.length) {
  console.error('Tra-Vel guide packet validation failed:');
  failures.forEach((message) => console.error(`- ${message}`));
  process.exit(1);
}

console.log(`Tra-Vel guide packet validation passed (${packetFiles.length} packet${packetFiles.length === 1 ? '' : 's'}).`);
