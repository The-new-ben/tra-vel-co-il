import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const packetDir = join(repoRoot, 'content', 'guides');
const schemaPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'guide-source-packet.schema.json');
const discoveryPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'discovery-demo.json');
const editorialDirectoryPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'editorial-directory.json');
const contentRegistryPath = join(repoRoot, 'content', 'seo', 'content-opportunity-registry.json');
const destinationTemplatePath = join(repoRoot, 'theme', 'tra-vel-v2', 'page-destination.php');
const guideHtmlHelperPath = join(repoRoot, 'theme', 'tra-vel-v2', 'inc', 'guide-html.php');
const smokeManifestPath = join(repoRoot, 'scripts', 'deploy', 'smoke-routes.tsv');
const failures = [];
const seenTopics = new Map();
const seenPaths = new Map();
const publishReadyPackets = new Map();
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
const destinationShellIds = ['main-content', 'map', 'decision-when', 'decision-areas', 'guide'];
const canonicalSiteOrigin = 'https://tra-vel.co.il';
const guideIntentValues = new Set(['smart', 'value', 'easy', 'romantic', 'family', 'adventure', 'surprise']);
const guideTripValues = new Set(['all', 'short', 'long']);
const guideAgentProducts = new Set(['package', 'packages', 'flights', 'hotels', 'insurance']);
const guideAgentScopes = new Set(['flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment']);
const privateUtilityPaths = new Set(['/saved/', '/account/']);
const sourcePacketOwnerTypes = new Set(['destination-hub', 'destination-support', 'decision-guide']);
const publicRegistryStatuses = new Set(['content-ready', 'live']);
let guideSchema = null;
let discovery = null;
let editorialDirectory = null;
let contentRegistry = null;
let destinationTemplate = '';
let guideHtmlHelper = '';
const publicInternalPaths = new Set();

function isIsoDate(value) {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const date = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

function fail(file, message) {
  failures.push(`${file}: ${message}`);
}

function decodeHtmlAttribute(value) {
  const named = { amp: '&', quot: '"', apos: "'", lt: '<', gt: '>' };
  return String(value || '').replace(/&(?:#(\d+)|#x([\da-f]+)|(amp|quot|apos|lt|gt));/gi, (entity, decimal, hexadecimal, name) => {
    if (decimal || hexadecimal) {
      const codePoint = decimal ? Number(decimal) : Number.parseInt(hexadecimal, 16);
      return Number.isInteger(codePoint) && codePoint >= 0 && codePoint <= 0x10ffff ? String.fromCodePoint(codePoint) : entity;
    }
    return named[String(name || '').toLowerCase()] || entity;
  });
}

function extractHtmlTagRecords(html) {
  const records = [];
  const input = String(html || '');
  const tags = [];
  let cursor = 0;
  while (cursor < input.length) {
    const tagStart = input.indexOf('<', cursor);
    if (tagStart < 0) break;
    if (input.startsWith('<!--', tagStart)) {
      const commentEnd = input.indexOf('-->', tagStart + 4);
      cursor = commentEnd < 0 ? input.length : commentEnd + 3;
      continue;
    }
    if (!/[a-z]/i.test(input[tagStart + 1] || '')) {
      cursor = tagStart + 1;
      continue;
    }
    let state = 'tag_name';
    let tagEnd = -1;
    for (let index = tagStart + 2; index < input.length; index += 1) {
      const character = input[index];
      const whitespace = /[\x20\t\r\n\f]/.test(character);
      if (state === 'attribute_value_double') {
        if (character === '"') state = 'after_attribute_value_quoted';
      } else if (state === 'attribute_value_single') {
        if (character === "'") state = 'after_attribute_value_quoted';
      } else if (state === 'before_attribute_value') {
        if (whitespace) continue;
        if (character === '"') state = 'attribute_value_double';
        else if (character === "'") state = 'attribute_value_single';
        else if (character === '>') tagEnd = index;
        else state = 'attribute_value_unquoted';
      } else if (state === 'attribute_value_unquoted') {
        if (character === '>') tagEnd = index;
        else if (whitespace) state = 'before_attribute_name';
      } else if (state === 'attribute_name') {
        if (character === '>') tagEnd = index;
        else if (character === '=') state = 'before_attribute_value';
        else if (whitespace) state = 'after_attribute_name';
      } else if (state === 'after_attribute_name') {
        if (character === '>') tagEnd = index;
        else if (character === '=') state = 'before_attribute_value';
        else if (!whitespace && character !== '/') state = 'attribute_name';
      } else if (state === 'after_attribute_value_quoted') {
        if (character === '>') tagEnd = index;
        else if (whitespace || character === '/') state = 'before_attribute_name';
        else state = 'attribute_name';
      } else if (state === 'before_attribute_name') {
        if (character === '>') tagEnd = index;
        else if (!whitespace && character !== '/') state = 'attribute_name';
      } else if (character === '>') {
        tagEnd = index;
      } else if (whitespace || character === '/') {
        state = 'before_attribute_name';
      }
      if (tagEnd >= 0) break;
    }
    if (tagEnd < 0) {
      cursor = tagStart + 1;
      continue;
    }
    tags.push(input.slice(tagStart, tagEnd + 1));
    cursor = tagEnd + 1;
  }
  for (const source of tags) {
    const nameMatch = source.match(/^<([a-z][a-z0-9:-]*)/i);
    if (!nameMatch) continue;
    const attributes = new Map();
    const duplicateAttributes = new Set();
    const attributeSource = source.slice(nameMatch[0].length, -1);
    const attributePattern = /(?:^|[\x20\t\r\n\f]+)([^\x20\t\r\n\f"'=<>`]+)(?:[\x20\t\r\n\f]*=[\x20\t\r\n\f]*(?:"([^"]*)"|'([^']*)'|([^\x20\t\r\n\f"'=<>`]+)))?/g;
    for (const match of attributeSource.matchAll(attributePattern)) {
      const name = String(match[1] || '').toLowerCase();
      if (!name || name === '/') continue;
      const value = match[2] ?? match[3] ?? match[4] ?? null;
      if (attributes.has(name)) duplicateAttributes.add(name);
      else attributes.set(name, value);
    }
    records.push({ name: nameMatch[1].toLowerCase(), source, attributes, duplicateAttributes });
  }
  return records;
}

function extractGuideContentIds(html) {
  const ids = [];
  const errors = [];
  for (const tag of extractHtmlTagRecords(html)) {
    const hasLiteralIdAttribute = /[\x20\t\r\n\f]id[\x20\t\r\n\f]*=/i.test(tag.source);
    if (hasLiteralIdAttribute && !tag.attributes.has('id')) errors.push(`tag contains an unparseable id attribute: ${tag.source.slice(0, 100)}.`);
    if (tag.duplicateAttributes.has('id')) errors.push(`tag contains duplicate id attributes: ${tag.source.slice(0, 100)}.`);
    if (!tag.attributes.has('id')) continue;
    const rawId = tag.attributes.get('id');
    if (typeof rawId !== 'string' || !rawId) {
      errors.push(`tag has an empty or valueless id attribute: ${tag.source.slice(0, 100)}.`);
      continue;
    }
    if (rawId.includes('&')) {
      errors.push(`tag id ${rawId} uses an HTML entity; use the literal stable ID.`);
      continue;
    }
    ids.push(rawId);
  }
  return { ids, errors };
}

function extractGuideLinks(html) {
  const links = [];
  const errors = [];
  for (const tag of extractHtmlTagRecords(html).filter(record => record.name === 'a')) {
    const hasLiteralHrefAttribute = /[\x20\t\r\n\f]href[\x20\t\r\n\f]*=/i.test(tag.source);
    if (hasLiteralHrefAttribute && !tag.attributes.has('href')) {
      errors.push(`anchor contains an unparseable href attribute: ${tag.source.slice(0, 100)}.`);
      continue;
    }
    if (tag.duplicateAttributes.has('href')) {
      errors.push(`anchor contains duplicate href attributes: ${tag.source.slice(0, 100)}.`);
      continue;
    }
    if (!tag.attributes.has('href')) continue;
    const rawHref = tag.attributes.get('href');
    if (typeof rawHref !== 'string' || !rawHref) {
      errors.push(`anchor has an empty or valueless href attribute: ${tag.source.slice(0, 100)}.`);
      continue;
    }
    const href = decodeHtmlAttribute(rawHref);
    if (href !== href.trim()) {
      errors.push(`anchor href has leading or trailing whitespace: ${rawHref}.`);
      continue;
    }
    if (String(rawHref).split(/[?#]/, 1)[0].includes('&')) {
      errors.push(`anchor href path must not hide characters behind HTML entities: ${rawHref}.`);
      continue;
    }
    if (/&(?:#[\da-fx]+|[a-z][a-z0-9]+);/i.test(href)) {
      errors.push(`anchor href contains an unsupported HTML entity: ${rawHref}.`);
      continue;
    }
    links.push({ href, rawHref, attributes: tag.attributes, source: tag.source });
  }
  return { links, errors };
}

function setValidator(values) {
  return value => values.has(value);
}

function integerValidator(minimum, maximum) {
  return value => /^(?:0|[1-9]\d*)$/.test(value) && Number(value) >= minimum && Number(value) <= maximum;
}

function numberValidator(minimum, maximum) {
  return value => /^-?(?:0|[1-9]\d*)(?:\.\d+)?$/.test(value) && Number(value) >= minimum && Number(value) <= maximum;
}

function currentIsoDateValidator(value) {
  return isIsoDate(value) && value >= projectToday;
}

function guideQueryContract(pathname, context) {
  const airport = setValidator(context.airportCodes);
  const destination = setValidator(context.destinationIds);
  const origin = value => /^[A-Z]{3}$/.test(value);
  const intent = setValidator(guideIntentValues);
  const boolean = value => /^(?:1|true)$/.test(value);
  const date = currentIsoDateValidator;
  const adults = integerValidator(1, 6);
  const children = integerValidator(0, 4);
  const rooms = integerValidator(1, 3);
  const routeControls = {
    intent,
    max_stops: integerValidator(0, 3),
    max_duration: integerValidator(60, 3000),
    allow_overnight: boolean,
  };
  const travelers = { adults, children };
  const contracts = {
    '/travel-map/': { destination },
    '/flights/': {
      origin,
      destination: airport,
      departure_date: date,
      return_date: date,
      ...travelers,
      direct: boolean,
      ...routeControls,
    },
    '/hotels/': {
      destination: airport,
      checkin: date,
      checkout: date,
      ...travelers,
      rooms,
      area: value => /^[a-z0-9-]{1,60}$/.test(value),
      intent,
    },
    '/packages/': {
      origin,
      destination: airport,
      departure_date: date,
      return_date: date,
      ...travelers,
      rooms,
      budget: integerValidator(200, 1600),
      trip: setValidator(guideTripValues),
      ...routeControls,
    },
    '/travel-insurance/': {
      trip_destination: destination,
      start_date: date,
      end_date: date,
      ...travelers,
      intent,
    },
    '/ai-planner/': {
      mode: value => /^(?:surprise|map_point)$/.test(value),
      destination,
      product: setValidator(guideAgentProducts),
      scope: value => {
        const values = value.split(',');
        return values.length > 0 && values.every(scope => guideAgentScopes.has(scope)) && new Set(values).size === values.length;
      },
      latitude: numberValidator(-90, 90),
      longitude: numberValidator(-180, 180),
      selection_id: value => /^[A-Za-z0-9_-]{8,80}$/.test(value),
      selection_kind: value => /^(?:destination|map_point)$/.test(value),
      origin,
      departure_date: date,
      return_date: date,
      ...travelers,
      rooms,
      budget: integerValidator(200, 1600),
      trip: setValidator(guideTripValues),
      ...routeControls,
    },
  };
  return contracts[pathname] || {};
}

function validateGuideInternalQuery(url, context) {
  const errors = [];
  const contract = guideQueryContract(url.pathname, context);
  const keys = [...url.searchParams.keys()];
  for (const key of new Set(keys)) {
    const values = url.searchParams.getAll(key);
    if (values.length !== 1) {
      errors.push(`query key ${key} must appear exactly once.`);
      continue;
    }
    if (!Object.prototype.hasOwnProperty.call(contract, key)) {
      errors.push(`query key ${key} is not supported on ${url.pathname}.`);
      continue;
    }
    if (!contract[key](values[0])) errors.push(`query value ${key}=${values[0]} is invalid for ${url.pathname}.`);
  }
  const requirePair = (first, second, allowSameDay = false) => {
    if (url.searchParams.has(first) !== url.searchParams.has(second)) errors.push(`query keys ${first} and ${second} must be supplied together.`);
    if (url.searchParams.has(first) && url.searchParams.has(second)) {
      const firstValue = url.searchParams.get(first);
      const secondValue = url.searchParams.get(second);
      if (secondValue < firstValue || (!allowSameDay && secondValue === firstValue)) errors.push(`query date ${second} must be ${allowSameDay ? 'on or ' : ''}after ${first}.`);
    }
  };
  if (url.pathname === '/flights/' || url.pathname === '/packages/') requirePair('departure_date', 'return_date');
  if (url.pathname === '/hotels/') requirePair('checkin', 'checkout');
  if (url.pathname === '/travel-insurance/') requirePair('start_date', 'end_date', true);
  if (url.pathname === '/ai-planner/') {
    requirePair('departure_date', 'return_date', url.searchParams.get('product') === 'insurance');
    if (url.searchParams.has('latitude') !== url.searchParams.has('longitude')) errors.push('query keys latitude and longitude must be supplied together.');
    if (url.searchParams.get('mode') === 'surprise' && url.searchParams.has('destination')) errors.push('surprise mode cannot carry a destination that the runtime intentionally ignores.');
    if (url.searchParams.get('mode') === 'map_point') {
      if (url.searchParams.get('selection_kind') !== 'map_point') errors.push('map_point mode requires selection_kind=map_point.');
      for (const key of ['selection_id', 'latitude', 'longitude']) {
        if (!url.searchParams.has(key)) errors.push(`map_point mode requires query key ${key}.`);
      }
      if (url.searchParams.has('destination')) errors.push('map_point mode cannot carry a destination before the point is identified.');
    }
    if (url.searchParams.get('selection_kind') === 'map_point' && url.searchParams.get('mode') !== 'map_point') {
      errors.push('selection_kind=map_point requires mode=map_point.');
    }
  }
  return errors;
}

function analyzeGuideLinks(html, canonicalPath, publicPaths, context) {
  const extracted = extractGuideLinks(html);
  const internal = [];
  const external = [];
  const localTargets = [];
  const errors = [...extracted.errors];
  for (const link of extracted.links) {
    if (link.href.startsWith('//')) {
      errors.push(`protocol-relative href is not allowed: ${link.href}.`);
      continue;
    }
    if (link.href.startsWith('#')) {
      if (!/^#[^\s#]+$/.test(link.href)) errors.push(`local fragment href is invalid: ${link.href}.`);
      else localTargets.push(link.href.slice(1));
      continue;
    }

    let url;
    if (link.href.startsWith('/')) {
      try { url = new URL(link.href, canonicalSiteOrigin); }
      catch { errors.push(`internal href is invalid: ${link.href}.`); continue; }
      const literalPath = link.href.split(/[?#]/, 1)[0];
      if (literalPath !== url.pathname) {
        errors.push(`internal href must use its canonical normalized path: ${link.href}.`);
        continue;
      }
    } else {
      if (!/^[a-z][a-z0-9+.-]*:/i.test(link.href)) {
        errors.push(`path-relative href is not allowed; use a root-relative path: ${link.href}.`);
        continue;
      }
      try { url = new URL(link.href); }
      catch { errors.push(`absolute href is invalid: ${link.href}.`); continue; }
    }
    if (url.protocol !== 'https:' || url.username || url.password) {
      errors.push(`href must use credential-free HTTPS: ${link.href}.`);
      continue;
    }
    if (url.hostname === 'www.tra-vel.co.il') {
      errors.push(`same-site href must use the canonical ${canonicalSiteOrigin} origin: ${link.href}.`);
      continue;
    }
    if (url.origin !== canonicalSiteOrigin) {
      external.push({ ...link, url });
      continue;
    }
    if (!publicPaths.has(url.pathname)) {
      errors.push(`complete guide links to non-public internal path ${url.pathname}; use a stable public hub until that route is published.`);
      continue;
    }
    for (const queryError of validateGuideInternalQuery(url, context)) errors.push(`${link.href}: ${queryError}`);
    if (url.pathname === canonicalPath && url.hash) localTargets.push(url.hash.slice(1));
    internal.push({ ...link, url });
  }
  return { internal, external, localTargets, errors };
}

function uniqueIndexableDecisionPaths(internalLinks, canonicalPath) {
  return new Set(
    internalLinks
      .map(link => link.url.pathname)
      .filter(pathname => pathname !== canonicalPath && !privateUtilityPaths.has(pathname))
  );
}

function guideAnchorCandidates(guideSlug, suffixes) {
  return [...suffixes, ...suffixes.map(suffix => `${guideSlug}-${suffix}`)];
}

function resolveGuideAnchor(contentIdSet, guideSlug, suffixes, fallback) {
  return guideAnchorCandidates(guideSlug, suffixes).find(candidate => contentIdSet.has(candidate)) || fallback;
}

const guideParserFixture = '<!-- <h2 id="comment-id"></h2> --><h2 title=\'1 > 0\' ID = \'bangkok-fit\'>Fit</h2><div data-id="not-a-real-id"></div><a data-href="/flights/"></a><a title="1 > 0" HREF = "/flights/?destination=BKK&amp;direct=true">Flight</a>';
const guideParserFixtureIds = extractGuideContentIds(guideParserFixture);
const guideParserFixtureLinks = extractGuideLinks(guideParserFixture);
if (guideParserFixtureIds.errors.length || guideParserFixtureIds.ids.length !== 1 || guideParserFixtureIds.ids[0] !== 'bangkok-fit') {
  failures.push('Actual HTML id parser self-test failed for whitespace, case, single quotes, comments, or data-id exclusion.');
}
if (guideParserFixtureLinks.errors.length || guideParserFixtureLinks.links.length !== 1 || guideParserFixtureLinks.links[0].href !== '/flights/?destination=BKK&direct=true') {
  failures.push('Actual HTML href parser self-test failed for whitespace, case, entities, or data-href exclusion.');
}
if (resolveGuideAnchor(new Set(guideParserFixtureIds.ids), 'bangkok', ['intro', 'fit', 'who'], 'intro') !== 'bangkok-fit') {
  failures.push('Nested Bangkok destination-prefixed anchor self-test failed.');
}

const guideLinkFixtureContext = {
  airportCodes: new Set(['HND']),
  destinationIds: new Set(['tokyo']),
};
const guideLinkFixturePaths = new Set(['/destinations/', '/travel-map/', '/flights/', '/hotels/', '/packages/', '/travel-insurance/', '/ai-planner/', '/saved/', '/account/']);
const validGuideLinks = analyzeGuideLinks(
  '<a href="/destinations/"></a><a href="/hotels/?destination=HND"></a><a href="https://tra-vel.co.il/travel-insurance/?trip_destination=tokyo"></a><a href="/ai-planner/?destination=tokyo&amp;product=insurance&amp;departure_date=2099-01-01&amp;return_date=2099-01-01"></a><a href="/ai-planner/?mode=map_point&amp;selection_kind=map_point&amp;selection_id=map_12345678&amp;latitude=35.6762&amp;longitude=139.6503"></a>',
  '/destinations/tokyo/',
  guideLinkFixturePaths,
  guideLinkFixtureContext
);
if (validGuideLinks.errors.length || validGuideLinks.internal.length !== 5) failures.push('Guide internal URL/query contract rejects valid neutral, packet-bound, insurance, or map-point links.');
for (const [label, html] of [
  ['path-relative href', '<a href="../hotels/"></a>'],
  ['protocol-relative href', '<a href="//evil.example/flights/"></a>'],
  ['same-origin non-public href', '<a href="https://tra-vel.co.il/not-public/"></a>'],
  ['quoted-greater-than tokenizer bypass', '<a title="1 > 0" href="/not-public/"></a>'],
  ["malformed unquoted-quote tokenizer bypass", "<a title=foo' href=\"/not-public/\"></a>"],
  ['cross-packet airport destination', '<a href="/hotels/?destination=ATH"></a>'],
  ['runtime-incompatible destination slug', '<a href="/hotels/?destination=tokyo"></a>'],
  ['cross-packet map destination', '<a href="/travel-map/?destination=athens"></a>'],
  ['cross-packet insurance destination', '<a href="/travel-insurance/?trip_destination=lisbon"></a>'],
  ['runtime-incompatible insurance key', '<a href="/travel-insurance/?destination=lisbon"></a>'],
  ['ignored package query aliases', '<a href="/flights/?route=TLV-ATH&amp;dates=flexible"></a>'],
  ['non-canonical hexadecimal coordinate', '<a href="/ai-planner/?mode=map_point&amp;selection_kind=map_point&amp;selection_id=map_12345678&amp;latitude=0x10&amp;longitude=139.6503"></a>'],
  ['incomplete map-point identity', '<a href="/ai-planner/?mode=map_point&amp;latitude=35.6762&amp;longitude=139.6503"></a>'],
  ['same-day non-insurance AI dates', '<a href="/ai-planner/?destination=tokyo&amp;product=hotels&amp;departure_date=2099-01-01&amp;return_date=2099-01-01"></a>'],
]) {
  if (!analyzeGuideLinks(html, '/destinations/tokyo/', guideLinkFixturePaths, guideLinkFixtureContext).errors.length) {
    failures.push(`Guide internal URL/query contract accepts ${label}.`);
  }
}

const privateDecisionFixture = analyzeGuideLinks(
  '<a href="/saved/"></a><a href="/account/"></a><a href="/flights/?destination=HND"></a><a href="/flights/"></a><a href="/hotels/?destination=HND"></a><a href="/travel-insurance/?trip_destination=tokyo"></a><a href="/ai-planner/?destination=tokyo"></a>',
  '/destinations/tokyo/',
  guideLinkFixturePaths,
  guideLinkFixtureContext
);
const privateDecisionPaths = uniqueIndexableDecisionPaths(privateDecisionFixture.internal, '/destinations/tokyo/');
if (privateDecisionFixture.errors.length || privateDecisionPaths.size !== 4 || privateDecisionPaths.has('/saved/') || privateDecisionPaths.has('/account/')) {
  failures.push('Indexable decision-path self-test must deduplicate route variants and exclude private utility paths.');
}

function isUnassignedEditorialIdentity(value) {
  const normalized = String(value || '').trim().toLowerCase();
  return /^(?:(?:reviewer|author)\s+)?(?:tbd|to be determined|unassigned|not assigned|pending\s+(?:reviewer|author))(?:\s+(?:reviewer|author))?$/i.test(normalized)
    || /(?:\u05d8\u05e8\u05dd\s+\u05de\u05d5\u05e0\u05d4|\u05dc\u05d0\s+\u05de\u05d5\u05e0\u05d4|\u05dc\u05d0\s+\u05d4\u05d5\u05e7\u05e6\u05d4|\u05dc\u05dc\u05d0\s+(?:\u05de\u05d7\u05d1\u05e8|\u05e2\u05d5\u05e8\u05da))/u.test(normalized);
}

if (!isUnassignedEditorialIdentity('TBD') || !isUnassignedEditorialIdentity('\u05d8\u05e8\u05dd \u05de\u05d5\u05e0\u05d4 \u05e2\u05d5\u05e8\u05da \u05de\u05d6\u05d5\u05d4\u05d4') || isUnassignedEditorialIdentity('\u05de\u05e2\u05e8\u05db\u05ea Tra-Vel')) {
  failures.push('Editorial identity placeholder detector self-test failed.');
}

function schemaTypeMatches(value, type) {
  if (type === 'object') return value !== null && typeof value === 'object' && !Array.isArray(value);
  if (type === 'array') return Array.isArray(value);
  if (type === 'integer') return Number.isInteger(value);
  if (type === 'number') return typeof value === 'number' && Number.isFinite(value);
  if (type === 'null') return value === null;
  return typeof value === type;
}

function validateSchemaValue(value, schema, path = '$') {
  const errors = [];
  if (!schema || typeof schema !== 'object') return errors;

  if (schema.type && !schemaTypeMatches(value, schema.type)) {
    return [`${path} must be ${schema.type}.`];
  }
  if ('const' in schema && JSON.stringify(value) !== JSON.stringify(schema.const)) {
    errors.push(`${path} must equal ${JSON.stringify(schema.const)}.`);
  }
  if (Array.isArray(schema.enum) && !schema.enum.some(option => JSON.stringify(option) === JSON.stringify(value))) {
    errors.push(`${path} must be one of ${schema.enum.map(option => JSON.stringify(option)).join(', ')}.`);
  }
  if (typeof value === 'string') {
    if (Number.isInteger(schema.minLength) && Array.from(value).length < schema.minLength) errors.push(`${path} must contain at least ${schema.minLength} characters.`);
    if (schema.pattern && !(new RegExp(schema.pattern, 'u')).test(value)) errors.push(`${path} does not match ${schema.pattern}.`);
    if (schema.format === 'date' && !isIsoDate(value)) errors.push(`${path} must be a valid ISO date.`);
  }
  if (typeof value === 'number' && Number.isFinite(schema.minimum) && value < schema.minimum) {
    errors.push(`${path} must be at least ${schema.minimum}.`);
  }
  if (Array.isArray(value)) {
    if (Number.isInteger(schema.minItems) && value.length < schema.minItems) errors.push(`${path} must contain at least ${schema.minItems} items.`);
    if (schema.uniqueItems === true) {
      const serialized = value.map(item => JSON.stringify(item));
      if (new Set(serialized).size !== serialized.length) errors.push(`${path} must contain unique items.`);
    }
    if (schema.items) value.forEach((item, index) => errors.push(...validateSchemaValue(item, schema.items, `${path}[${index}]`)));
  }
  if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
    const properties = schema.properties || {};
    for (const key of schema.required || []) {
      if (!Object.prototype.hasOwnProperty.call(value, key)) errors.push(`${path}.${key} is required.`);
    }
    if (schema.additionalProperties === false) {
      for (const key of Object.keys(value)) {
        if (!Object.prototype.hasOwnProperty.call(properties, key)) errors.push(`${path}.${key} is not allowed.`);
      }
    }
    for (const [key, propertySchema] of Object.entries(properties)) {
      if (Object.prototype.hasOwnProperty.call(value, key)) errors.push(...validateSchemaValue(value[key], propertySchema, `${path}.${key}`));
    }
  }
  return errors;
}

function supportingGuideBindingErrors(guide, registered, owner) {
  const errors = [];
  if (!registered) errors.push(`published supporting guide ${guide?.guide_path || '(missing path)'} has no publish-ready guide packet.`);
  else if (registered.packet.mapState !== guide?.map_state) errors.push(`published supporting guide ${guide?.guide_path} uses map state ${guide?.map_state}, but its packet uses ${registered.packet.mapState}.`);
  if (!owner) errors.push(`published supporting guide ${guide?.guide_path || '(missing path)'} has no content registry owner.`);
  else {
    if (owner.pageType !== 'destination-support') errors.push(`supporting guide ${guide?.guide_path} must be owned by a destination-support registry entry.`);
    if (!publicRegistryStatuses.has(owner.status)) errors.push(`supporting guide ${guide?.guide_path} requires a content-ready or live registry owner.`);
    if (owner.parentPath !== guide?.parent_path) errors.push(`supporting guide ${guide?.guide_path} parent_path does not match its registry owner.`);
    if (owner.mapState !== guide?.map_state) errors.push(`supporting guide ${guide?.guide_path} map state does not match its registry owner.`);
  }
  return errors;
}

function publishReadyPacketBindingErrors(canonicalPath, registered, owner, context) {
  const errors = [];
  const packet = registered?.packet;

  if (!owner) {
    if (packet) errors.push(`publish-ready guide packet ${canonicalPath} has no exact content registry owner.`);
    return errors;
  }

  if (owner.pageType === 'transactional-cluster') {
    if (packet) errors.push(`transactional owner ${canonicalPath} must use the dedicated transactional publication gate, not a publish-ready guide packet.`);
    return errors;
  }

  if (!sourcePacketOwnerTypes.has(owner.pageType)) {
    if (packet) errors.push(`publish-ready guide packet ${canonicalPath} is bound to unsupported owner type ${owner.pageType}.`);
    return errors;
  }

  if (!publicRegistryStatuses.has(owner.status)) {
    errors.push(`publish-ready guide ${canonicalPath} requires a content-ready or live registry owner.`);
  }
  if (!packet) {
    errors.push(`${owner.pageType} owner ${canonicalPath} with status ${owner.status} has no publish-ready guide packet.`);
    return errors;
  }
  if (packet.status !== 'publish-ready') errors.push(`guide packet ${canonicalPath} must have status publish-ready.`);
  if (packet.canonicalPath !== canonicalPath || owner.canonicalPath !== canonicalPath) errors.push(`guide packet ${canonicalPath} does not exactly match its registry owner canonical path.`);
  if (packet.mapState !== owner.mapState) errors.push(`guide packet ${canonicalPath} mapState does not match its registry owner.`);

  if (owner.pageType === 'destination-support') {
    const supportingGuide = context.publishedSupportingEntries.find(entry => entry.guide_path === canonicalPath);
    if (!supportingGuide) errors.push(`publish-ready destination-support guide ${canonicalPath} is absent from supporting_guides.`);
    else {
      if (supportingGuide.parent_path !== owner.parentPath) errors.push(`publish-ready destination-support guide ${canonicalPath} has a parent mismatch.`);
      if (supportingGuide.map_state !== packet.mapState) errors.push(`publish-ready destination-support guide ${canonicalPath} has a map-state mismatch.`);
    }
  } else if (owner.pageType === 'destination-hub') {
    const destination = context.publishedDirectoryEntries.find(entry => entry.guide_path === canonicalPath);
    if (!destination) errors.push(`publish-ready destination guide ${canonicalPath} is absent from the published editorial directory.`);
    else if (destination.map_state !== packet.mapState || destination.map_state !== owner.mapState) {
      errors.push(`publish-ready destination guide ${canonicalPath} has a map-state mismatch.`);
    }
  } else if (owner.pageType === 'decision-guide') {
    const match = canonicalPath.match(/^\/guides\/([a-z0-9-]+)\/[a-z0-9-]+\/$/);
    if (!match) {
      errors.push(`decision-guide ${canonicalPath} must use /guides/{cluster}/{guide}/.`);
    } else {
      const semanticParentPath = `/destinations/${match[1]}/`;
      const semanticParent = context.registryEntriesByPath.get(semanticParentPath);
      if (owner.cluster !== match[1]) errors.push(`decision-guide ${canonicalPath} cluster does not match its canonical path.`);
      if (owner.parentPath !== semanticParentPath) errors.push(`decision-guide ${canonicalPath} must use semantic parent ${semanticParentPath}.`);
      if (!semanticParent || semanticParent.pageType !== 'destination-hub') {
        errors.push(`decision-guide ${canonicalPath} has no exact semantic destination-hub parent.`);
      } else {
        if (semanticParent.cluster !== owner.cluster) errors.push(`decision-guide ${canonicalPath} cluster does not match its semantic parent.`);
        if (!publicRegistryStatuses.has(semanticParent.status)) errors.push(`decision-guide ${canonicalPath} semantic parent must be content-ready or live.`);
      }
    }
  }

  return errors;
}

if (!existsSync(schemaPath)) failures.push('Guide packet JSON schema is missing.');
else {
  try { guideSchema = JSON.parse(readFileSync(schemaPath, 'utf8')); }
  catch (error) { failures.push(`Guide packet JSON schema is invalid: ${error.message}`); }
}

if (!existsSync(discoveryPath)) failures.push('Discovery destination contract is missing.');
else {
  try { discovery = JSON.parse(readFileSync(discoveryPath, 'utf8')); }
  catch (error) { failures.push(`Discovery destination contract is invalid: ${error.message}`); }
}

if (!existsSync(editorialDirectoryPath)) failures.push('Editorial destination directory is missing.');
else {
  try { editorialDirectory = JSON.parse(readFileSync(editorialDirectoryPath, 'utf8')); }
  catch (error) { failures.push(`Editorial destination directory is invalid: ${error.message}`); }
}

if (!existsSync(contentRegistryPath)) failures.push('Content opportunity registry is missing.');
else {
  try { contentRegistry = JSON.parse(readFileSync(contentRegistryPath, 'utf8')); }
  catch (error) { failures.push(`Content opportunity registry is invalid: ${error.message}`); }
}

if (!existsSync(smokeManifestPath)) failures.push('Public smoke-route manifest is missing.');
else {
  for (const line of readFileSync(smokeManifestPath, 'utf8').split(/\r?\n/).filter(Boolean)) {
    const [path] = line.split('\t');
    if (/^\/(?:[a-z0-9-]+\/)*$/i.test(path || '')) publicInternalPaths.add(path);
    else failures.push(`Public smoke-route manifest contains an invalid path: ${path || '(empty)'}.`);
  }
}

for (const entry of editorialDirectory?.destinations || []) {
  if (entry?.guide_status === 'published' && typeof entry?.guide_path === 'string') publicInternalPaths.add(entry.guide_path);
}
for (const entry of editorialDirectory?.supporting_guides || []) {
  if (entry?.guide_status === 'published' && typeof entry?.guide_path === 'string') publicInternalPaths.add(entry.guide_path);
}

if (editorialDirectory && !Array.isArray(editorialDirectory.supporting_guides)) {
  failures.push('Editorial destination directory supporting_guides must be an array.');
}

if (!existsSync(destinationTemplatePath)) failures.push('Destination guide template is missing.');
else destinationTemplate = readFileSync(destinationTemplatePath, 'utf8');
if (!existsSync(guideHtmlHelperPath)) failures.push('Destination guide HTML parser helper is missing.');
else guideHtmlHelper = readFileSync(guideHtmlHelperPath, 'utf8');

for (const id of destinationShellIds) {
  if (!destinationTemplate.includes(`id="${id}"`)) failures.push(`Destination guide template is missing shell anchor #${id}.`);
}
for (const binding of ['guide_intro_anchor', 'guide_flights_anchor', 'guide_costs_anchor', 'guide_insurance_anchor', 'guide_faq_anchor']) {
  if (!destinationTemplate.includes(`$${binding}`)) failures.push(`Destination guide template is missing dynamic anchor binding $${binding}.`);
}
if (!destinationTemplate.includes('$guide_anchor_candidates') || !destinationTemplate.includes("$guide_slug . '-' . $suffix")) {
  failures.push('Destination guide template does not resolve destination-prefixed editorial anchors.');
}
if (!destinationTemplate.includes('$guide_content_ids') || !destinationTemplate.includes('array_key_exists( $candidate, $guide_content_ids )') || !destinationTemplate.includes('tra_vel_v2_extract_guide_content_ids( $guide_content_html )')) {
  failures.push('Destination guide template does not resolve anchors from actual tag-scoped id attributes.');
}
for (const marker of ['tra_vel_v2_tokenize_guide_html_tags', "$state", "'attribute_value_double'", "if ( '>' === $character )", '/[\\x20\\t\\r\\n\\f]id']) {
  if (!guideHtmlHelper.includes(marker)) failures.push(`Destination guide HTML parser is missing quote-aware marker ${marker}.`);
}
if (destinationTemplate.includes("strpos( $guide_content_html, 'id=\"'") || destinationTemplate.includes('/<[a-z][a-z0-9:-]*(?:\\s[^<>]*?)?>/i')) {
  failures.push('Destination guide template regressed to substring-based id detection.');
}

const discoveryDestinationsById = new Map(
  Array.isArray(discovery?.destinations)
    ? discovery.destinations.filter(destination => destination?.id).map(destination => [destination.id, destination])
    : []
);

if (!existsSync(packetDir)) failures.push('content/guides is missing.');
const packetFiles = existsSync(packetDir)
  ? readdirSync(packetDir).filter((name) => name.endsWith('.sources.json')).sort()
  : [];
if (!packetFiles.length) failures.push('At least one destination source packet is required.');

for (const file of packetFiles) {
  let packet;
  try { packet = JSON.parse(readFileSync(join(packetDir, file), 'utf8')); }
  catch (error) { fail(file, `invalid JSON: ${error.message}`); continue; }

  if (guideSchema) {
    for (const message of validateSchemaValue(packet, guideSchema)) fail(file, `schema ${message}`);
  }

  for (const key of ['schemaVersion', 'id', 'locale', 'title', 'excerpt', 'author', 'reviewer', 'reviewMethod', 'primaryTopic', 'canonicalPath', 'status', 'wordTargetMin', 'checkedAt', 'mapState', 'sections', 'sources', 'facts']) {
    if (!(key in packet)) fail(file, `missing ${key}`);
  }
  if (packet.schemaVersion !== 1) fail(file, 'schemaVersion must be 1.');
  if (packet.locale !== 'he-IL') fail(file, 'locale must be he-IL.');
  if (typeof packet.title !== 'string' || packet.title.trim().length < 2) fail(file, 'title is required.');
  if (typeof packet.excerpt !== 'string' || packet.excerpt.trim().length < 50) fail(file, 'excerpt must contain at least 50 characters.');
  if (/[—–]/u.test(packet.excerpt || '')) fail(file, 'public excerpts must not use em dash or en dash punctuation.');
  if (/מפת העריכה|במחקר|בדיקת מערכת|מדריך דגל/u.test(packet.excerpt || '')) fail(file, 'public excerpts contain internal project language.');
  if (typeof packet.author !== 'string' || packet.author.trim().length < 2) fail(file, 'author is required.');
  if (typeof packet.reviewer !== 'string' || packet.reviewer.trim().length < 2) fail(file, 'reviewer is required.');
  if (typeof packet.reviewMethod !== 'string' || packet.reviewMethod.trim().length < 30) fail(file, 'reviewMethod must describe the editorial review process.');
  if (!/^[a-z0-9-]+$/.test(packet.id || '')) fail(file, 'id must be a lowercase slug.');
  if (!/^\/(?:[a-z0-9-]+\/)+$/.test(packet.canonicalPath || '')) fail(file, 'canonicalPath must be a clean, trailing-slash route.');
  if (!/^[a-z0-9-]+$/.test(packet.mapState || '')) fail(file, 'mapState must be a lowercase destination slug.');
  if (!['research', 'source-ready', 'editorial-review', 'publish-ready'].includes(packet.status)) fail(file, 'status is invalid.');
  if (!Number.isInteger(packet.wordTargetMin) || packet.wordTargetMin < 5000) fail(file, 'wordTargetMin must be at least 5000.');
  if (!isIsoDate(packet.checkedAt)) fail(file, 'checkedAt must be a valid ISO date.');
  if (isIsoDate(packet.checkedAt) && packet.checkedAt > projectToday) fail(file, 'checkedAt cannot be in the future in the project timezone.');
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
    if (isIsoDate(source.checkedAt) && source.checkedAt > projectToday) {
      fail(file, `${source.id || 'source'} checkedAt cannot be in the future in the project timezone.`);
    }
    if (isIsoDate(source.checkedAt) && isIsoDate(packet.checkedAt) && source.checkedAt > packet.checkedAt) {
      fail(file, `${source.id || 'source'} checkedAt cannot be later than the packet review date.`);
    }
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

  if (['editorial-review', 'publish-ready'].includes(packet.status) && !packet.contentPath) {
    fail(file, `${packet.status} packets require contentPath.`);
  }
  const contentPathLocale = typeof packet.contentPath === 'string'
    ? packet.contentPath.match(/^content\/guides\/[a-z0-9-]+(?:\.([a-z]{2}))?\.(?:md|html)$/)?.[1]
    : null;
  const packetLanguage = typeof packet.locale === 'string' ? packet.locale.split('-')[0].toLowerCase() : '';
  if (contentPathLocale && contentPathLocale !== packetLanguage) fail(file, `contentPath locale .${contentPathLocale} does not match packet locale ${packet.locale}.`);
  const completeGuide = ['editorial-review', 'publish-ready'].includes(packet.status);
  const mappedDestination = discoveryDestinationsById.get(packet.mapState);
  const mappedAirportCode = mappedDestination?.airport?.code;
  if (completeGuide && !mappedDestination) fail(file, `${packet.status} mapState ${packet.mapState || '(empty)'} is absent from discovery destinations.`);
  if (completeGuide && (typeof mappedAirportCode !== 'string' || !/^[A-Z]{3}$/.test(mappedAirportCode))) {
    fail(file, `${packet.status} mapState ${packet.mapState || '(empty)'} needs one canonical discovery airport code.`);
  }
  if (packet.status === 'publish-ready') {
    if (isUnassignedEditorialIdentity(packet.author)) fail(file, 'publish-ready author cannot be an unassigned or TBD placeholder.');
    if (isUnassignedEditorialIdentity(packet.reviewer)) fail(file, 'publish-ready reviewer cannot be an unassigned or TBD placeholder.');
    if (!Array.isArray(discovery?.route_sets?.[packet.mapState]) || discovery.route_sets[packet.mapState].length < 2) fail(file, `publish-ready mapState ${packet.mapState || '(empty)'} needs at least two discovery routes.`);
    publishReadyPackets.set(packet.canonicalPath, { file, packet });
  }
  if (packet.contentPath) {
    const contentPath = resolve(repoRoot, packet.contentPath);
    if (!contentPath.startsWith(join(repoRoot, 'content', 'guides'))) fail(file, 'contentPath must stay inside content/guides.');
    else if (!existsSync(contentPath)) fail(file, `guide content is missing: ${packet.contentPath}.`);
    else {
      const content = readFileSync(contentPath, 'utf8');
      const visibleText = content
        .replace(/<[^>]+>/g, ' ')
        .replace(/&(?:[a-z]+|#\d+|#x[\da-f]+);/gi, ' ');
      const words = visibleText.match(/[\p{L}\p{N}][\p{L}\p{N}\u05be'’-]*/gu) || [];
      const hebrewWords = words.filter((word) => /[\u0590-\u05ff]/u.test(word));
      const h2Count = (content.match(/<h2\b/gi) || []).length;
      const decisionTables = (content.match(/<table\b/gi) || []).length;
      const contentIdResult = extractGuideContentIds(content);
      for (const message of contentIdResult.errors) fail(file, message);
      const contentIds = contentIdResult.ids;
      const packetGuideLinkContext = {
        destinationIds: new Set(mappedDestination ? [packet.mapState] : []),
        airportCodes: new Set(typeof mappedAirportCode === 'string' && /^[A-Z]{3}$/.test(mappedAirportCode) ? [mappedAirportCode] : []),
      };
      const linkAnalysis = analyzeGuideLinks(content, packet.canonicalPath, publicInternalPaths, packetGuideLinkContext);
      if (['editorial-review', 'publish-ready'].includes(packet.status)) {
        for (const message of linkAnalysis.errors) fail(file, message);
      }
      const indexableDecisionPaths = uniqueIndexableDecisionPaths(linkAnalysis.internal, packet.canonicalPath);
      const sourcedExternalLinks = linkAnalysis.external.filter(link => {
        const relTokens = String(link.attributes.get('rel') || '').toLowerCase().split(/[\x20\t\r\n\f]+/).filter(Boolean);
        return relTokens.includes('external') && relTokens.includes('noopener');
      });
      if (['editorial-review', 'publish-ready'].includes(packet.status)) {
        for (const link of linkAnalysis.external.filter(link => !sourcedExternalLinks.includes(link))) {
          fail(file, `external guide link must declare rel="external noopener" and belong to the source packet: ${link.href}.`);
        }
      }
      const sourcedLinks = sourcedExternalLinks.length;
      const externalUrls = sourcedExternalLinks.map(link => link.href);
      const uniqueExternalUrls = [...new Set(externalUrls)];
      const duplicateContentIds = [...new Set(contentIds.filter((id, index) => contentIds.indexOf(id) !== index))];
      if (duplicateContentIds.length) fail(file, `content contains duplicate IDs: ${duplicateContentIds.join(', ')}.`);
      if (['editorial-review', 'publish-ready'].includes(packet.status)) {
        const composedIds = [...destinationShellIds, ...contentIds];
        const duplicateComposedIds = [...new Set(composedIds.filter((id, index) => composedIds.indexOf(id) !== index))];
        if (duplicateComposedIds.length) fail(file, `destination shell and guide content duplicate IDs: ${duplicateComposedIds.join(', ')}.`);

        const composedIdSet = new Set(composedIds);
        const contentIdSet = new Set(contentIds);
        const guideSlug = String(packet.canonicalPath || '').split('/').filter(Boolean).at(-1) || '';
        const shellTargets = [
          'map',
          'decision-when',
          'decision-areas',
          'guide',
          resolveGuideAnchor(contentIdSet, guideSlug, ['intro', 'fit', 'who'], 'intro'),
          resolveGuideAnchor(contentIdSet, guideSlug, ['flights', 'flight', 'flight-choice', 'flight-chain', 'airport', 'airport-choice'], 'flights'),
          resolveGuideAnchor(contentIdSet, guideSlug, ['costs', 'budget'], 'costs'),
          resolveGuideAnchor(contentIdSet, guideSlug, ['insurance', 'health'], 'insurance'),
          resolveGuideAnchor(contentIdSet, guideSlug, ['faq', 'booking', 'booking-order'], 'faq')
        ];
        for (const target of [...new Set([...shellTargets, ...linkAnalysis.localTargets])]) {
          if (!composedIdSet.has(target)) fail(file, `composed destination link points to missing #${target}.`);
        }
      }
      if (words.length < packet.wordTargetMin) fail(file, `content has ${words.length} words; ${packet.wordTargetMin} required.`);
      if (hebrewWords.length / Math.max(words.length, 1) < 0.75) fail(file, 'flagship content must be predominantly Hebrew.');
      if (h2Count < 12) fail(file, `content has ${h2Count} H2 sections; at least 12 required.`);
      if (indexableDecisionPaths.size < 4) fail(file, `content has ${indexableDecisionPaths.size} unique indexable decision paths; at least 4 required (private utility links do not count).`);
      if (sourcedLinks < 6) fail(file, `content has ${sourcedLinks} visible source links; at least 6 required.`);
      if (uniqueExternalUrls.length < 6) fail(file, `content cites ${uniqueExternalUrls.length} unique packet sources; at least 6 required.`);
      if (['editorial-review', 'publish-ready'].includes(packet.status) && decisionTables < 3) fail(file, `${packet.status} content has ${decisionTables} decision tables; at least 3 required.`);
      if (/[—–]/u.test(content)) fail(file, 'public guide content must not use em dash or en dash punctuation.');
      if (/מפת העריכה|במחקר|בדיקת מערכת|מדריך דגל/u.test(content)) fail(file, 'public guide content contains internal project language.');
      for (const url of externalUrls) {
        if (!sourceUrls.has(url)) fail(file, `content cites an external URL that is absent from the source packet: ${url}.`);
      }
      if (/<(?:script|iframe)\b/i.test(content)) fail(file, 'guide content must not embed scripts or iframes.');
      if (/FAQPage/.test(content)) fail(file, 'guide content must not include FAQPage markup.');
    }
  }
}

const publishedDirectoryEntries = Array.isArray(editorialDirectory?.destinations)
  ? editorialDirectory.destinations.filter(destination => destination?.guide_status === 'published')
  : [];
const publishedSupportingEntries = Array.isArray(editorialDirectory?.supporting_guides)
  ? editorialDirectory.supporting_guides.filter(guide => guide?.guide_status === 'published')
  : [];
const registryEntriesByPath = new Map(
  Array.isArray(contentRegistry?.entries)
    ? contentRegistry.entries.filter(entry => entry?.canonicalPath).map(entry => [entry.canonicalPath, entry])
    : []
);
for (const destination of publishedDirectoryEntries) {
  const registered = publishReadyPackets.get(destination.guide_path);
  if (!registered) fail('editorial-directory.json', `published guide ${destination.guide_path || '(missing path)'} has no publish-ready guide packet.`);
  else if (registered.packet.mapState !== destination.map_state) fail('editorial-directory.json', `published guide ${destination.guide_path} uses map state ${destination.map_state}, but its packet uses ${registered.packet.mapState}.`);
}
for (const guide of publishedSupportingEntries) {
  const registered = publishReadyPackets.get(guide.guide_path);
  const owner = registryEntriesByPath.get(guide.guide_path);
  for (const message of supportingGuideBindingErrors(guide, registered, owner)) fail('editorial-directory.json', message);
}

const supportingBindingFixture = {
  guide_path: '/destinations/thailand/bangkok/', parent_path: '/destinations/thailand/', map_state: 'bangkok'
};
const supportingPacketFixture = { packet: { mapState: 'bangkok' } };
const supportingOwnerFixture = {
  pageType: 'destination-support', status: 'content-ready', parentPath: '/destinations/thailand/', mapState: 'bangkok'
};
if (supportingGuideBindingErrors(supportingBindingFixture, supportingPacketFixture, supportingOwnerFixture).length) {
  failures.push('Supporting-guide binding validator rejects a valid publication triple.');
}
if (!supportingGuideBindingErrors(supportingBindingFixture, supportingPacketFixture, { ...supportingOwnerFixture, parentPath: '/destinations/athens/' }).length) {
  failures.push('Supporting-guide binding validator accepts a mismatched registry parent.');
}
if (!supportingGuideBindingErrors(supportingBindingFixture, null, supportingOwnerFixture).length) {
  failures.push('Supporting-guide binding validator accepts a missing publish-ready packet.');
}

const decisionParentFixture = {
  id: 'vienna-guide', canonicalPath: '/destinations/vienna/', pageType: 'destination-hub',
  cluster: 'vienna', parentPath: '/destinations/', status: 'content-ready', mapState: 'vienna'
};
const decisionOwnerFixture = {
  id: 'vienna-areas', canonicalPath: '/guides/vienna/where-to-stay/', pageType: 'decision-guide',
  cluster: 'vienna', parentPath: '/destinations/vienna/', status: 'content-ready', mapState: 'vienna'
};
const decisionRegisteredFixture = {
  file: 'vienna-areas.sources.json',
  packet: { canonicalPath: '/guides/vienna/where-to-stay/', status: 'publish-ready', mapState: 'vienna' }
};
const decisionRegistryFixture = new Map([
  [decisionParentFixture.canonicalPath, decisionParentFixture],
  [decisionOwnerFixture.canonicalPath, decisionOwnerFixture],
]);
const emptyDirectoryContext = {
  publishedDirectoryEntries: [], publishedSupportingEntries: [], registryEntriesByPath: decisionRegistryFixture
};
if (publishReadyPacketBindingErrors(decisionOwnerFixture.canonicalPath, decisionRegisteredFixture, decisionOwnerFixture, emptyDirectoryContext).length) {
  failures.push('Decision-guide packet binding rejects an exact public owner, map state, and semantic destination parent.');
}
if (!publishReadyPacketBindingErrors(decisionOwnerFixture.canonicalPath, null, { ...decisionOwnerFixture, status: 'live' }, emptyDirectoryContext).length) {
  failures.push('Decision-guide packet binding accepts a live decision owner without a publish-ready packet.');
}

const transactionalOwnerFixture = {
  id: 'vienna-flights', canonicalPath: '/flights/vienna/', pageType: 'transactional-cluster',
  cluster: 'vienna', parentPath: '/flights/', status: 'content-ready', mapState: 'vienna'
};
const transactionalRegisteredFixture = {
  file: 'vienna-flights.sources.json',
  packet: { canonicalPath: '/flights/vienna/', status: 'publish-ready', mapState: 'vienna' }
};
if (!publishReadyPacketBindingErrors(transactionalOwnerFixture.canonicalPath, transactionalRegisteredFixture, transactionalOwnerFixture, emptyDirectoryContext).length) {
  failures.push('Guide packet binding accepts a publish-ready packet for a transactional owner.');
}

const destinationOwnerFixture = {
  id: 'athens-guide', canonicalPath: '/destinations/athens/', pageType: 'destination-hub',
  cluster: 'athens', parentPath: '/destinations/', status: 'content-ready', mapState: 'athens'
};
const destinationRegisteredFixture = {
  file: 'athens-2026.sources.json',
  packet: { canonicalPath: '/destinations/athens/', status: 'publish-ready', mapState: 'athens' }
};
const destinationContextFixture = {
  publishedDirectoryEntries: [{ guide_path: '/destinations/athens/', map_state: 'athens' }],
  publishedSupportingEntries: [],
  registryEntriesByPath: new Map([[destinationOwnerFixture.canonicalPath, destinationOwnerFixture]]),
};
if (publishReadyPacketBindingErrors(destinationOwnerFixture.canonicalPath, destinationRegisteredFixture, destinationOwnerFixture, destinationContextFixture).length) {
  failures.push('Destination packet binding rejects the existing directory-backed destination contract.');
}
if (!publishReadyPacketBindingErrors(destinationOwnerFixture.canonicalPath, destinationRegisteredFixture, destinationOwnerFixture, { ...destinationContextFixture, publishedDirectoryEntries: [] }).length) {
  failures.push('Destination packet binding accepts a destination absent from the published editorial directory.');
}

const bindingPaths = new Set([
  ...publishReadyPackets.keys(),
  ...[...registryEntriesByPath.values()]
    .filter(owner => sourcePacketOwnerTypes.has(owner.pageType) && publicRegistryStatuses.has(owner.status))
    .map(owner => owner.canonicalPath),
]);
const bindingContext = { publishedDirectoryEntries, publishedSupportingEntries, registryEntriesByPath };
for (const canonicalPath of bindingPaths) {
  const registered = publishReadyPackets.get(canonicalPath);
  const owner = registryEntriesByPath.get(canonicalPath);
  for (const message of publishReadyPacketBindingErrors(canonicalPath, registered, owner, bindingContext)) {
    fail(registered?.file || 'content-opportunity-registry.json', message);
  }
}

if (failures.length) {
  console.error('Tra-Vel guide packet validation failed:');
  failures.forEach((message) => console.error(`- ${message}`));
  process.exit(1);
}

console.log(`Tra-Vel guide packet validation passed (${packetFiles.length} packet${packetFiles.length === 1 ? '' : 's'}).`);
