(function () {
	'use strict';

	function element(tagName, className, text) {
		var node = document.createElement(tagName);
		if (className) {
			node.className = className;
		}
		if (typeof text === 'string') {
			node.textContent = text;
		}
		return node;
	}

	function text(config, key, fallback) {
		var strings = config && config.proposalStrings && typeof config.proposalStrings === 'object'
			? config.proposalStrings
			: {};
		return typeof strings[key] === 'string' && strings[key] ? strings[key] : fallback;
	}

	function humanize(value) {
		var normalized = String(value || '').replace(/[_-]+/g, ' ');
		return normalized.charAt(0).toUpperCase() + normalized.slice(1);
	}

	function caseId(item) {
		return String(item && (item.case_id || item.case_uuid || item.id) || '');
	}

	function caseReference(item) {
		return String(item && (item.reference || item.reference_code || item.case_id) || '-');
	}

	function caseSummary(item) {
		return item && item.summary && typeof item.summary === 'object' ? item.summary : {};
	}

	function caseAuthoringContext(item) {
		var source = item && item.source && typeof item.source === 'object' ? item.source : {};
		var version = Number(item && (item.version || item.case_version));
		var revision = Number(item && (item.case_revision || item.current_revision));
		var digest = String(source.request_digest || (item && item.latest_request_digest) || '').toLowerCase();
		return {
			expected_case_version: Number.isInteger(version) && version > 0 ? version : 0,
			expected_case_revision: Number.isInteger(revision) && revision > 0 ? revision : 0,
			expected_request_digest: /^[a-f0-9]{64}$/.test(digest) ? digest : '',
		};
	}

	function validAuthoringContext(context) {
		return Boolean(context
			&& Number.isInteger(context.expected_case_version) && context.expected_case_version > 0
			&& Number.isInteger(context.expected_case_revision) && context.expected_case_revision > 0
			&& /^[a-f0-9]{64}$/.test(String(context.expected_request_digest || '')));
	}

	function formatDate(value) {
		if (!value) {
			return '-';
		}
		var parsed = new Date(value);
		if (Number.isNaN(parsed.getTime())) {
			return String(value);
		}
		try {
			return new Intl.DateTimeFormat(document.documentElement.lang || 'en', {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(parsed);
		} catch (error) {
			return parsed.toLocaleString();
		}
	}

	function formatMoney(minor, currency) {
		if (!Number.isSafeInteger(minor) || minor < 0 || !currency) {
			return '';
		}
		try {
			return new Intl.NumberFormat(document.documentElement.lang || 'en', {
				style: 'currency',
				currency: currency,
			}).format(minor / 100);
		} catch (error) {
			return currency + ' ' + (minor / 100).toFixed(2);
		}
	}

	function proposalUrl(config, item) {
		return String(config.restUrl || '').replace(/\/$/, '') + '/' + encodeURIComponent(caseId(item)) + '/assisted-proposals';
	}

	function operatorCaseUrl(config, item) {
		return String(config.restUrl || '').replace(/\/$/, '') + '/' + encodeURIComponent(caseId(item));
	}

	function compositionUrl(config, item, editing) {
		return proposalUrl(config, item)
			+ (editing ? '/' + encodeURIComponent(editing.proposal_id) : '')
			+ '/compose';
	}

	function evidenceAttestationUrl(config, item) {
		return proposalUrl(config, item) + '/evidence-attestation';
	}

	function withdrawalUrl(config, item, proposalId) {
		return proposalUrl(config, item) + '/' + encodeURIComponent(proposalId) + '/withdraw';
	}

	function cloneDraft(draft) {
		return JSON.parse(JSON.stringify(draft));
	}

	function retainDraft(state, draft, reason) {
		if (!state || !draft) {
			return false;
		}
		var serialized = JSON.stringify(draft);
		state.retainedDrafts = Array.isArray(state.retainedDrafts) ? state.retainedDrafts : [];
		var existing = state.retainedDrafts.find(function (retained) {
			return retained && retained.serialized === serialized;
		});
		if (existing) {
			state.retentionCapacityReached = false;
			return true;
		}
		if (state.retainedDrafts.length >= 3) {
			state.retentionCapacityReached = true;
			return false;
		}
		state.retainedDrafts.unshift({
			draft: cloneDraft(draft),
			serialized: serialized,
			reason: String(reason || 'A prior authored draft'),
		});
		state.retentionCapacityReached = false;
		return true;
	}

	function invalidateEvidenceAttestation(state) {
		if (state) {
			state.evidenceAttestation = null;
		}
	}

	function invalidateSourceIndexes(state, indexes) {
		invalidateEvidenceAttestation(state);
		var unique = Array.from(new Set((indexes || []).map(Number).filter(function (index) {
			return Number.isInteger(index) && index >= 0 && index < state.draft.sources.length;
		})));
		unique.forEach(function (index) {
			state.draft.sources[index].revalidated_now = false;
			var attestation = state.root && typeof state.root.querySelector === 'function'
				? state.root.querySelector('[name="source-' + index + '-revalidated"]')
				: null;
			if (attestation) {
				attestation.checked = false;
			}
		});
	}

	function invalidateComponentSources(state, component, extraIndexes) {
		invalidateSourceIndexes(state, (component.source_indexes || []).concat(extraIndexes || []));
	}

	function invalidateAllComponentSources(state) {
		state.draft.components.forEach(function (component) {
			invalidateComponentSources(state, component);
		});
	}

	function removeSourceAt(state, index) {
		var sourceIndex = Number(index);
		var inUse = state.draft.components.some(function (component) {
			return (component.source_indexes || []).map(Number).indexOf(sourceIndex) !== -1;
		});
		if (inUse) {
			state.notice = {
				type: 'error',
				message: 'This evidence is still cited by a trip component. Reassign that component before removing the evidence.',
			};
			state.noticeFocus = true;
			return false;
		}
		state.draft.sources.splice(sourceIndex, 1);
		invalidateEvidenceAttestation(state);
		state.draft.components.forEach(function (component) {
			component.source_indexes = (component.source_indexes || []).map(Number).map(function (value) {
				return value > sourceIndex ? value - 1 : value;
			});
		});
		state.notice = null;
		return true;
	}

	function idempotencyKey(scope) {
		var suffix = window.crypto && typeof window.crypto.randomUUID === 'function'
			? window.crypto.randomUUID()
			: Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14);
		return String(scope || 'proposal') + ':' + suffix;
	}

	async function request(config, url, options) {
		var requestOptions = options || {};
		var headers = {
			Accept: 'application/json',
			'X-WP-Nonce': String(config.nonce || ''),
		};
		if (requestOptions.body) {
			headers['Content-Type'] = 'application/json';
		}
		var response;
		try {
			response = await window.fetch(url, {
				method: requestOptions.method || 'GET',
				headers: headers,
				credentials: 'same-origin',
				body: requestOptions.body ? JSON.stringify(requestOptions.body) : undefined,
			});
		} catch (networkError) {
			var unavailable = new Error('The proposal service could not be reached. Retry will reuse the same protected request key.');
			unavailable.network = true;
			unavailable.code = 'tra_vel_assisted_proposal_network_uncertain';
			unavailable.outcomeUncertain = Boolean(requestOptions.method && requestOptions.method !== 'GET');
			throw unavailable;
		}
		var payload = null;
		try {
			payload = await response.json();
		} catch (parseError) {
			payload = null;
		}
		if (!response.ok) {
			var apiError = new Error(payload && payload.message ? payload.message : 'The proposal request could not be completed.');
			apiError.status = response.status;
			apiError.code = payload && payload.code ? payload.code : '';
			apiError.data = payload && payload.data ? payload.data : {};
			apiError.outcomeUncertain = Boolean(
				requestOptions.method && requestOptions.method !== 'GET'
				&& (response.status === 408 || response.status >= 500)
			);
			throw apiError;
		}
		if (!payload || typeof payload !== 'object') {
			var uncertain = new Error(requestOptions.method && requestOptions.method !== 'GET'
				? 'The server responded without a verifiable result. The outcome is not yet confirmed; retry will reuse the same protected request key.'
				: 'The proposal service returned an unreadable response.');
			uncertain.code = 'tra_vel_assisted_proposal_response_uncertain';
			uncertain.outcomeUncertain = Boolean(requestOptions.method && requestOptions.method !== 'GET');
			throw uncertain;
		}
		return payload;
	}

	var proposalKeys = ['contract_version', 'proposal_id', 'case_id', 'reference', 'status', 'version', 'revision', 'published_revision', 'position', 'addresses', 'title', 'summary', 'why_it_fits', 'trade_offs', 'route', 'itinerary', 'components', 'ledger', 'sources', 'source_set_digest', 'freshness', 'unresolved_items', 'traveler_disposition', 'next_actions', 'disclosure', 'created_at', 'published_at', 'expires_at'];
	var sourceKeys = ['contract_version', 'source_id', 'provider_code', 'source_type', 'relationship', 'public_label', 'supplier_name', 'seller_name', 'source_reference', 'source_url', 'observed_at', 'fresh_until', 'evidence_digest', 'requires_revalidation'];

	function plainObject(value) {
		return Boolean(value && typeof value === 'object' && !Array.isArray(value));
	}

	function exactKeys(value, keys) {
		if (!plainObject(value)) {
			return false;
		}
		var actual = Object.keys(value).sort();
		var expected = keys.slice().sort();
		return actual.length === expected.length && actual.every(function (key, index) { return key === expected[index]; });
	}

	function isUuid(value) {
		return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || ''));
	}

	function isDigest(value) {
		return /^[a-f0-9]{64}$/.test(String(value || ''));
	}

	function boundedList(value, minimum, maximum) {
		return Array.isArray(value) && value.length >= minimum && value.length <= maximum;
	}

	function boundedString(value, minimum, maximum) {
		return typeof value === 'string' && value.length >= minimum && value.length <= maximum;
	}

	function enumValue(value, allowed) {
		return allowed.indexOf(value) !== -1;
	}

	function uniqueStrings(value, minimum, maximum, validator) {
		if (!boundedList(value, minimum, maximum)) {
			return false;
		}
		var seen = {};
		return value.every(function (entry) {
			if (typeof entry !== 'string' || seen[entry] || (validator && !validator(entry))) {
				return false;
			}
			seen[entry] = true;
			return true;
		});
	}

	function isDateTime(value) {
		return typeof value === 'string'
			&& /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/.test(value)
			&& Number.isFinite(Date.parse(value));
	}

	function isPublicHttpsUrl(value) {
		return boundedString(value, 1, 500)
			&& /^https:\/\/(?![^\/?#\s]*@)[^\/?#\s:]+(?:\/[^?#\s]*)?$/.test(value);
	}

	function sameStringSet(actual, expected) {
		if (!uniqueStrings(actual, expected.length, expected.length)) {
			return false;
		}
		return actual.slice().sort().every(function (value, index) {
			return value === expected.slice().sort()[index];
		});
	}

	function nextActionsValid(status, disposition, actions) {
		if (!uniqueStrings(actions, 0, 4, function (action) {
			return enumValue(action, ['review', 'request_changes', 'authorize_contact', 'decline']);
		})) {
			return false;
		}
		if (status !== 'available') {
			return disposition === 'unavailable' && actions.length === 0;
		}
		if (disposition === 'awaiting_review') {
			return sameStringSet(actions, ['review', 'request_changes', 'authorize_contact', 'decline']);
		}
		if (disposition === 'reviewed') {
			return sameStringSet(actions, ['request_changes', 'authorize_contact', 'decline']);
		}
		return enumValue(disposition, ['changes_requested', 'contact_authorized', 'declined']) && actions.length === 0;
	}

	function sourceShapeValid(source, sourceIds) {
		var publicTypes = ['public_supplier_page', 'official_information'];
		if (!exactKeys(source, sourceKeys)
			|| source.contract_version !== '1.0.0'
			|| !isUuid(source.source_id) || sourceIds[source.source_id]
			|| !boundedString(source.provider_code, 1, 64) || !/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(source.provider_code)
			|| !enumValue(source.source_type, ['connected_api', 'supplier_portal', 'supplier_written_quote', 'public_supplier_page', 'official_information'])
			|| !enumValue(source.relationship, ['operator_attested', 'public_reference'])
			|| !boundedString(source.public_label, 1, 190)
			|| !boundedString(source.supplier_name, 0, 190) || !boundedString(source.seller_name, 0, 190)
			|| !boundedString(source.source_reference, 0, 190) || !/^(?:[A-Za-z0-9._:-]{1,190})?$/.test(source.source_reference)
			|| !isDateTime(source.observed_at) || !isDateTime(source.fresh_until)
			|| Date.parse(source.fresh_until) <= Date.parse(source.observed_at)
			|| !isDigest(source.evidence_digest) || source.requires_revalidation !== true) {
			return false;
		}
		var publicSource = publicTypes.indexOf(source.source_type) !== -1;
		if (publicSource) {
			return source.relationship === 'public_reference' && isPublicHttpsUrl(source.source_url);
		}
		return source.relationship === 'operator_attested' && source.source_url === null && boundedString(source.source_reference, 1, 190);
	}

	function priceShapeValid(price) {
		if (!exactKeys(price, ['priced', 'total_for_party_minor', 'currency', 'basis', 'taxes', 'fees'])
			|| typeof price.priced !== 'boolean'
			|| !enumValue(price.taxes, ['included', 'excluded', 'unknown'])
			|| !enumValue(price.fees, ['included', 'excluded', 'unknown'])) {
			return false;
		}
		if (!price.priced) {
			return price.total_for_party_minor === null && price.currency === null && price.basis === 'not_priced'
				&& price.taxes === 'unknown' && price.fees === 'unknown';
		}
		return Number.isSafeInteger(price.total_for_party_minor) && price.total_for_party_minor >= 0 && price.total_for_party_minor <= 1000000000000
			&& enumValue(price.currency, ['ILS', 'USD', 'EUR'])
			&& enumValue(price.basis, ['trip_total', 'stay_total', 'ticket_total', 'activity_total', 'item_total']);
	}

	function ledgerMatchesComponents(ledger, components) {
		var priced = components.filter(function (component) { return component.price.priced; });
		var unpriced = components.filter(function (component) { return !component.price.priced; }).map(function (component) { return component.component_key; }).sort();
		var currencies = Array.from(new Set(priced.map(function (component) { return component.price.currency; })));
		var total = priced.reduce(function (sum, component) { return sum + component.price.total_for_party_minor; }, 0);
		var complete = priced.length === components.length && priced.every(function (component) {
			return component.price.taxes === 'included' && component.price.fees === 'included';
		});
		return currencies.length <= 1 && Number.isSafeInteger(total) && total <= 1000000000000
			&& ledger.currency === (currencies[0] || null)
			&& ledger.priced_total_minor === total
			&& ledger.priced_component_count === priced.length
			&& uniqueStrings(ledger.unpriced_component_keys, unpriced.length, unpriced.length, function (key) { return /^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(key) && key.length <= 64; })
			&& ledger.unpriced_component_keys.slice().sort().every(function (key, index) { return key === unpriced[index]; })
			&& ledger.complete_pricing === complete;
	}

	function proposalShapeValid(proposal, context) {
		if (!exactKeys(proposal, proposalKeys) || proposal.contract_version !== '1.0.0'
			|| !isUuid(proposal.proposal_id) || !isUuid(proposal.case_id)
			|| !/^TVP-(?:[A-Z0-9]{8}|[A-Z0-9]{12})$/.test(String(proposal.reference || ''))
			|| !enumValue(proposal.status, ['draft', 'available', 'withdrawn', 'expired', 'superseded'])
			|| !Number.isSafeInteger(proposal.version) || !Number.isSafeInteger(proposal.revision) || !Number.isSafeInteger(proposal.published_revision)
			|| proposal.version < 1 || proposal.version < proposal.revision || proposal.revision < 1 || proposal.published_revision < 0
			|| (proposal.status !== 'draft' && proposal.published_revision !== proposal.revision)
			|| !exactKeys(proposal.addresses, ['case_revision', 'request_digest'])
			|| !Number.isSafeInteger(proposal.addresses.case_revision) || proposal.addresses.case_revision < 1 || !isDigest(proposal.addresses.request_digest)
			|| !enumValue(proposal.position, ['best_value', 'lowest_friction', 'most_flexible', 'most_memorable', 'custom'])) {
			return false;
		}
		if (!boundedString(proposal.title, 1, 160) || !boundedString(proposal.summary, 1, 800)
			|| !boundedList(proposal.why_it_fits, 1, 6) || proposal.why_it_fits.some(function (value) { return !boundedString(value, 1, 240); })
			|| !boundedList(proposal.trade_offs, 1, 6) || proposal.trade_offs.some(function (value) { return !boundedString(value, 1, 240); })
			|| !exactKeys(proposal.route, ['origin', 'destinations', 'legs']) || !boundedString(proposal.route.origin, 1, 120)
			|| !uniqueStrings(proposal.route.destinations, 1, 8, function (value) { return boundedString(value, 1, 80); }) || !boundedList(proposal.route.legs, 0, 12)
			|| !boundedList(proposal.itinerary, 1, 31) || !boundedList(proposal.components, 1, 16)
			|| !boundedList(proposal.sources, 1, 32) || !boundedList(proposal.unresolved_items, 0, 16)
			|| !isDigest(proposal.source_set_digest)) {
			return false;
		}
		if (!exactKeys(proposal.ledger, ['contract_version', 'currency', 'priced_total_minor', 'priced_component_count', 'unpriced_component_keys', 'complete_pricing', 'calculation_digest'])
			|| proposal.ledger.contract_version !== '1.0.0' || !enumValue(proposal.ledger.currency, ['ILS', 'USD', 'EUR', null])
			|| !Number.isSafeInteger(proposal.ledger.priced_total_minor) || proposal.ledger.priced_total_minor < 0 || proposal.ledger.priced_total_minor > 1000000000000
			|| !Number.isInteger(proposal.ledger.priced_component_count) || proposal.ledger.priced_component_count < 0 || proposal.ledger.priced_component_count > 16
			|| typeof proposal.ledger.complete_pricing !== 'boolean' || !isDigest(proposal.ledger.calculation_digest)
			|| !exactKeys(proposal.freshness, ['checked_at', 'expires_at', 'requires_revalidation']) || !isDateTime(proposal.freshness.checked_at) || !isDateTime(proposal.freshness.expires_at) || proposal.freshness.requires_revalidation !== true
			|| Date.parse(proposal.freshness.expires_at) <= Date.parse(proposal.freshness.checked_at)) {
			return false;
		}
		if (!exactKeys(proposal.disclosure, ['commercial_state', 'final_quote_required', 'message'])
			|| proposal.disclosure.commercial_state !== 'non_binding_assisted_proposal' || proposal.disclosure.final_quote_required !== true
			|| proposal.disclosure.message !== 'Final price, availability, and terms are provided only after revalidation in a personal quote.'
			|| !isDateTime(proposal.created_at)
			|| !nextActionsValid(proposal.status, proposal.traveler_disposition, proposal.next_actions)) {
			return false;
		}
		if (context && context.case_id && String(proposal.case_id) !== String(context.case_id)) {
			return false;
		}
		if (context && context.proposal_id && String(proposal.proposal_id) !== String(context.proposal_id)) {
			return false;
		}
		if (context && Number.isInteger(context.expected_version) && proposal.version !== context.expected_version + 1) {
			return false;
		}
		if (context && context.expected_status && proposal.status !== context.expected_status
			&& !(context.allow_superseded_replay === true && context.expected_status === 'available' && proposal.status === 'superseded')) {
			return false;
		}
		if (context && Number.isInteger(context.expected_case_revision) && proposal.addresses.case_revision !== context.expected_case_revision) {
			return false;
		}
		if (context && context.expected_request_digest && proposal.addresses.request_digest !== context.expected_request_digest) {
			return false;
		}
		if (context && Number.isInteger(context.expected_revision) && proposal.revision !== context.expected_revision) {
			return false;
		}
		if (proposal.status === 'draft') {
			if (proposal.published_revision !== 0 || proposal.published_at !== null || proposal.expires_at !== null) {
				return false;
			}
		} else if (!isDateTime(proposal.published_at) || !isDateTime(proposal.expires_at)
			|| Date.parse(proposal.published_at) < Date.parse(proposal.freshness.checked_at)
			|| Date.parse(proposal.published_at) > Date.parse(proposal.expires_at)
			|| Date.parse(proposal.expires_at) !== Date.parse(proposal.freshness.expires_at)) {
			return false;
		}
		var legSequences = {};
		if (proposal.route.legs.some(function (leg) {
			if (!exactKeys(leg, ['sequence', 'from', 'to', 'mode']) || !Number.isInteger(leg.sequence) || leg.sequence < 1 || leg.sequence > 12 || legSequences[leg.sequence]
				|| !boundedString(leg.from, 1, 120) || !boundedString(leg.to, 1, 120) || !enumValue(leg.mode, ['flight', 'rail', 'road', 'ferry', 'walk', 'other'])) {
				return true;
			}
			legSequences[leg.sequence] = true;
			return false;
		})) {
			return false;
		}
		var sourceIds = {};
		if (proposal.sources.some(function (source) {
			if (!sourceShapeValid(source, sourceIds)) {
				return true;
			}
			sourceIds[source.source_id] = true;
			return false;
		})) {
			return false;
		}
		var componentKeys = {};
		if (proposal.components.some(function (component) {
			if (!exactKeys(component, ['component_key', 'category', 'title', 'description', 'price', 'conditions', 'source_ids', 'requires_revalidation'])
				|| !priceShapeValid(component.price)
				|| !exactKeys(component.conditions, ['cancellation', 'changes', 'baggage_or_inclusions'])
				|| !boundedString(component.component_key, 1, 64) || !/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(component.component_key) || componentKeys[component.component_key]
				|| !enumValue(component.category, ['flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment'])
				|| !boundedString(component.title, 1, 200) || !boundedString(component.description, 1, 800)
				|| !boundedString(component.conditions.cancellation, 1, 500) || !boundedString(component.conditions.changes, 1, 500) || !boundedString(component.conditions.baggage_or_inclusions, 1, 500)
				|| !uniqueStrings(component.source_ids, 1, 8, function (sourceId) { return isUuid(sourceId) && sourceIds[sourceId]; }) || component.requires_revalidation !== true
				|| (component.price.priced && !component.source_ids.some(function (sourceId) { return proposal.sources.find(function (source) { return source.source_id === sourceId; }).source_type !== 'official_information'; }))) {
				return true;
			}
			componentKeys[component.component_key] = true;
			return false;
		})) {
			return false;
		}
		if (!ledgerMatchesComponents(proposal.ledger, proposal.components)) {
			return false;
		}
		var itineraryDays = {};
		if (proposal.itinerary.some(function (day) {
			if (!exactKeys(day, ['day', 'place', 'title', 'component_keys']) || !Number.isInteger(day.day) || day.day < 1 || day.day > 365 || itineraryDays[day.day]
				|| !boundedString(day.place, 1, 120) || !boundedString(day.title, 1, 200)
				|| !uniqueStrings(day.component_keys, 0, 16, function (key) { return Boolean(componentKeys[key]); })) {
				return true;
			}
			itineraryDays[day.day] = true;
			return false;
		})) {
			return false;
		}
		var unresolvedCodes = {};
		if (proposal.unresolved_items.some(function (gap) {
			if (!exactKeys(gap, ['code', 'label']) || !enumValue(gap.code, ['unpriced_component', 'taxes_unknown', 'fees_unknown', 'availability_revalidation', 'policy_revalidation', 'schedule_revalidation', 'other'])
				|| unresolvedCodes[gap.code] || !boundedString(gap.label, 1, 240)) {
				return true;
			}
			unresolvedCodes[gap.code] = true;
			return false;
		})) {
			return false;
		}
		var requiredGaps = ['availability_revalidation'];
		if (proposal.components.some(function (component) { return !component.price.priced; })) { requiredGaps.push('unpriced_component'); }
		if (proposal.components.some(function (component) { return component.price.priced && component.price.taxes !== 'included'; })) { requiredGaps.push('taxes_unknown'); }
		if (proposal.components.some(function (component) { return component.price.priced && component.price.fees !== 'included'; })) { requiredGaps.push('fees_unknown'); }
		if (requiredGaps.some(function (code) { return !unresolvedCodes[code]; })) {
			return false;
		}
		var latestObservation = Math.max.apply(null, proposal.sources.map(function (source) { return Date.parse(source.observed_at); }));
		var earliestSourceExpiry = Math.min.apply(null, proposal.sources.map(function (source) { return Date.parse(source.fresh_until); }));
		if (Date.parse(proposal.freshness.checked_at) !== latestObservation || Date.parse(proposal.freshness.expires_at) > earliestSourceExpiry) {
			return false;
		}
		return true;
	}

	function mutationProposal(payload, context) {
		var proposal = payload && payload.proposal;
		var validationContext = plainObject(context) ? Object.assign({}, context) : {};
		if (payload && payload.replayed === true && validationContext.expected_status === 'available') {
			validationContext.allow_superseded_replay = true;
		}
		if (!exactKeys(payload, ['proposal', 'replayed']) || typeof payload.replayed !== 'boolean' || !proposalShapeValid(proposal, validationContext)) {
			var uncertain = new Error('The server response did not contain a verifiable proposal. The outcome is not yet confirmed; retry will reuse the same protected request key.');
			uncertain.code = 'tra_vel_assisted_proposal_response_uncertain';
			uncertain.outcomeUncertain = true;
			throw uncertain;
		}
		return proposal;
	}

	function proposalList(payload, context) {
		function invalidList() {
			var error = new Error('The proposal service returned an invalid proposal list. Existing options remain unchanged.');
			error.code = 'tra_vel_assisted_proposal_list_invalid';
			return error;
		}
		if (!exactKeys(payload, ['proposals', 'meta']) || !exactKeys(payload.meta, ['count']) || !Array.isArray(payload.proposals) || payload.proposals.length > 12 || payload.meta.count !== payload.proposals.length) {
			throw invalidList();
		}
		var seen = {};
		return payload.proposals.map(function (proposal) {
			if (!proposalShapeValid(proposal, context || {}) || seen[proposal.proposal_id]) {
				throw invalidList();
			}
			seen[proposal.proposal_id] = true;
			return proposal;
		});
	}

	function mergeProposalMonotonic(proposals, incoming) {
		var existing = (proposals || []).find(function (proposal) {
			return proposal.proposal_id === incoming.proposal_id;
		});
		var selected = existing && Number(existing.version) > Number(incoming.version) ? existing : incoming;
		return [selected].concat((proposals || []).filter(function (proposal) {
			return proposal.proposal_id !== incoming.proposal_id;
		}));
	}

	async function fetchProposalList(state) {
		var payload = await request(state.config, proposalUrl(state.config, state.item) + '?per_page=12');
		return proposalList(payload, {case_id: caseId(state.item)});
	}

	async function reconcileProposalState(state, proposalId) {
		var proposals;
		try {
			proposals = await fetchProposalList(state);
		} catch (error) {
			if (error && error.status === 403 && error.code === 'tra_vel_assisted_proposal_assignment_forbidden') {
				return {proposal: null, accessMoved: true};
			}
			var uncertain = new Error('The protected mutation was replayed, but its latest proposal state could not be reconciled. Retry will reuse the exact protected request.');
			uncertain.code = 'tra_vel_assisted_proposal_reconcile_required';
			uncertain.outcomeUncertain = true;
			uncertain.cause = error;
			throw uncertain;
		}
		var current = proposals.find(function (proposal) {
			return proposal.proposal_id === proposalId;
		});
		if (!current) {
			var uncertain = new Error('The operation was replayed, but the current proposal state could not be confirmed. Existing options remain unchanged; retry will reuse the same protected request key.');
			uncertain.code = 'tra_vel_assisted_proposal_replay_unconfirmed';
			uncertain.outcomeUncertain = true;
			throw uncertain;
		}
		state.proposals = proposals;
		return {proposal: current, accessMoved: false};
	}

	function completeAccessMovedReplay(state, kind, proposalId) {
		state.submitting = false;
		state.pending = null;
		state.uncertain = null;
		state.conflict = false;
		state.accessMoved = true;
		state.proposals = [];
		state.editing = null;
		state.draft = defaultDraft(state.item);
		state.authoringContext = caseAuthoringContext(state.item);
		state.evidenceAttestation = null;
		state.dirty = false;
		state.confirmWithdraw = '';
		state.withdrawPending = {};
		state.notice = {
			type: 'info',
			message: 'The exact protected request is confirmed, but this case is now assigned to another operator. Local proposal history and editing were closed without importing stale state.',
			code: kind === 'withdraw' ? 'tra_vel_assisted_proposal_withdraw_confirmed_access_moved' : 'tra_vel_assisted_proposal_publish_confirmed_access_moved',
		};
		state.noticeFocus = true;
	}

	function proposalExpectedVersion(editing) {
		return editing ? Number(editing.expected_version) : 0;
	}

	function splitList(value) {
		var seen = {};
		return String(value || '').split(/[\n,]+/).map(function (part) {
			return part.trim();
		}).filter(function (part) {
			var key = part.toLocaleLowerCase();
			if (!part || seen[key]) {
				return false;
			}
			seen[key] = true;
			return true;
		});
	}

	function splitLines(value) {
		return String(value || '').split(/\r?\n/).map(function (line) {
			return line.trim();
		}).filter(Boolean);
	}

	function stableKey(value, fallback) {
		var key = String(value || '').toLocaleLowerCase().trim()
			.replace(/[^a-z0-9_-]+/g, '-')
			.replace(/[-_]{2,}/g, '-')
			.replace(/^[-_]+|[-_]+$/g, '');
		return key || fallback;
	}

	function majorToMinor(value) {
		var normalized = String(value || '').trim().replace(',', '.');
		if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(normalized)) {
			return null;
		}
		var parts = normalized.split('.');
		var whole = Number(parts[0]);
		var fraction = Number(String(parts[1] || '').padEnd(2, '0'));
		var minor = whole * 100 + fraction;
		return Number.isSafeInteger(minor) ? minor : null;
	}

	function minorToMajor(value) {
		var minor = Number(value);
		if (!Number.isSafeInteger(minor) || minor < 0) {
			return '';
		}
		var whole = Math.floor(minor / 100);
		var fraction = String(minor % 100).padStart(2, '0');
		return fraction === '00' ? String(whole) : String(whole) + '.' + fraction;
	}

	function defaultDraft(item) {
		var summary = caseSummary(item);
		var destinations = Array.isArray(summary.destinations) ? summary.destinations.filter(Boolean) : [];
		var destination = destinations[0] || 'היעד שבחרתם';
		var scope = Array.isArray(summary.scope) ? summary.scope : [];
		var category = scope[0] || 'flights';
		var currency = summary.budget && ['ILS', 'USD', 'EUR'].indexOf(summary.budget.currency) !== -1
			? summary.budget.currency
			: 'ILS';
		return {
			position: 'best_value',
			title: 'חופשה אישית ל' + destination,
			summary: 'אפשרות אישית ומסודרת לחופשה, עם רכיבים ממקורות שייבדקו שוב לפני הרכישה.',
			why_it_fits: 'המסלול מתאים ליעד ולפרטי הנסיעה שנמסרו.',
			trade_offs: 'המחיר, הזמינות והתנאים הסופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה.',
			origin: summary.origin || 'תל אביב',
			destinations: destinations.length ? destinations.join(', ') : '',
			route_legs: [],
			currency: currency,
			sources: [{
				provider_code: 'supplier-source',
				source_type: 'supplier_written_quote',
				relationship: 'operator_attested',
				public_label: 'מקור מסחרי שנבדק',
				supplier_name: '',
				seller_name: 'Tra-Vel',
				source_reference: '',
				source_url: '',
				freshness_minutes: 60,
				revalidated_now: false,
			}],
			components: [{
				component_key: stableKey(category + '-option', 'trip-option'),
				category: category,
				title: 'רכיב נסיעה שנבדק',
				description: 'פרטי הרכיב יוצגו לפי המקור המצורף וייבדקו מחדש לפני הרכישה.',
				priced: false,
				amount_major: '',
				basis: category === 'accommodation' ? 'stay_total' : (category === 'activities' ? 'activity_total' : 'trip_total'),
				taxes: 'unknown',
				fees: 'unknown',
				cancellation: 'תנאי הביטול יאושרו בהצעת המחיר הסופית.',
				changes: 'אפשרויות שינוי יאושרו בהצעת המחיר הסופית.',
				inclusions: 'התכולה המדויקת תאושר לפי המקור ובהצעת המחיר הסופית.',
				source_indexes: [0],
			}],
			itinerary: [{
				day: 1,
				place: destination,
				title: 'הגעה והתארגנות',
				component_keys: stableKey(category + '-option', 'trip-option'),
			}],
			extra_gaps: [],
		};
	}

	function draftFromProposal(item, proposal, config) {
		var draft = defaultDraft(item);
		var sources = Array.isArray(proposal.sources) ? proposal.sources : [];
		draft.position = proposal.position || draft.position;
		draft.title = proposal.title || '';
		draft.summary = proposal.summary || '';
		draft.why_it_fits = Array.isArray(proposal.why_it_fits) ? proposal.why_it_fits.join('\n') : '';
		draft.trade_offs = Array.isArray(proposal.trade_offs) ? proposal.trade_offs.join('\n') : '';
		draft.origin = proposal.route && proposal.route.origin ? proposal.route.origin : draft.origin;
		draft.destinations = proposal.route && Array.isArray(proposal.route.destinations) ? proposal.route.destinations.join(', ') : draft.destinations;
		draft.route_legs = proposal.route && Array.isArray(proposal.route.legs) ? proposal.route.legs.map(function (leg) {
			return {sequence: Number(leg.sequence), from: leg.from, to: leg.to, mode: leg.mode};
		}) : [];
		draft.currency = proposal.ledger && proposal.ledger.currency ? proposal.ledger.currency : draft.currency;
		draft.sources = sources.map(function (source) {
			var maximum = Number(config.proposal.sourceTtlMinutes[source.source_type] || 60);
			return {
				provider_code: source.provider_code || 'source',
				source_type: source.source_type || 'supplier_written_quote',
				relationship: source.relationship || 'operator_attested',
				public_label: source.public_label || '',
				supplier_name: source.supplier_name || '',
				seller_name: source.seller_name || '',
				source_reference: source.source_reference || '',
				source_url: source.source_url || '',
				freshness_minutes: Math.min(60, maximum),
				revalidated_now: false,
			};
		});
		if (!draft.sources.length) {
			draft.sources = defaultDraft(item).sources;
		}
		draft.components = (Array.isArray(proposal.components) ? proposal.components : []).map(function (component, index) {
			var sourceIndexes = sources.map(function (source, sourceIndex) {
				return Array.isArray(component.source_ids) && component.source_ids.indexOf(source.source_id) !== -1 ? sourceIndex : -1;
			}).filter(function (sourceIndex) { return sourceIndex >= 0; });
			return {
				component_key: component.component_key || 'component-' + (index + 1),
				category: component.category || 'activities',
				title: component.title || '',
				description: component.description || '',
				priced: Boolean(component.price && component.price.priced),
				amount_major: component.price && component.price.priced ? minorToMajor(component.price.total_for_party_minor) : '',
				basis: component.price && component.price.priced ? component.price.basis : 'trip_total',
				taxes: component.price && component.price.priced ? component.price.taxes : 'unknown',
				fees: component.price && component.price.priced ? component.price.fees : 'unknown',
				cancellation: component.conditions ? component.conditions.cancellation : '',
				changes: component.conditions ? component.conditions.changes : '',
				inclusions: component.conditions ? component.conditions.baggage_or_inclusions : '',
				source_indexes: sourceIndexes.length ? sourceIndexes : [0],
			};
		});
		if (!draft.components.length) {
			draft.components = defaultDraft(item).components;
		}
		draft.itinerary = (Array.isArray(proposal.itinerary) ? proposal.itinerary : []).map(function (day) {
			return {day: Number(day.day), place: day.place || '', title: day.title || '', component_keys: Array.isArray(day.component_keys) ? day.component_keys.join(', ') : ''};
		});
		if (!draft.itinerary.length) {
			draft.itinerary = defaultDraft(item).itinerary;
		}
		var automatic = ['availability_revalidation', 'unpriced_component', 'taxes_unknown', 'fees_unknown'];
		draft.extra_gaps = Array.isArray(proposal.unresolved_items) ? proposal.unresolved_items.filter(function (gap) {
			return automatic.indexOf(gap.code) === -1;
		}).map(function (gap) {
			return {code: gap.code || 'other', label: gap.label || ''};
		}) : [];
		return draft;
	}

	function fieldId(state, suffix) {
		return 'tra-vel-proposal-' + state.instance + '-' + suffix;
	}

	function field(parent, state, options) {
		var wrap = element('div', 'tra-vel-proposal-field' + (options.wide ? ' is-wide' : ''));
		var id = fieldId(state, options.id);
		var label = element('label', '', options.label);
		label.htmlFor = id;
		wrap.appendChild(label);
		var control;
		if (options.type === 'textarea') {
			control = element('textarea');
			control.rows = options.rows || 3;
			control.value = options.value || '';
		} else if (options.type === 'select') {
			control = element('select');
			(options.options || []).forEach(function (option) {
				var node = element('option', '', option.label);
				node.value = option.value;
				node.selected = String(option.value) === String(options.value);
				control.appendChild(node);
			});
		} else {
			control = element('input');
			control.type = options.type || 'text';
			if (options.type === 'checkbox') {
				control.checked = Boolean(options.value);
			} else {
				control.value = options.value === null || typeof options.value === 'undefined' ? '' : String(options.value);
			}
		}
		control.id = id;
		control.name = options.id;
		if (options.required) {
			control.required = true;
		}
		if (options.maxLength) {
			control.maxLength = options.maxLength;
		}
		if (typeof options.min !== 'undefined') {
			control.min = String(options.min);
		}
		if (typeof options.max !== 'undefined') {
			control.max = String(options.max);
		}
		if (options.step) {
			control.step = options.step;
		}
		if (options.pattern) {
			control.pattern = options.pattern;
		}
		if (options.placeholder) {
			control.placeholder = options.placeholder;
		}
		if (options.inputMode) {
			control.inputMode = options.inputMode;
		}
		if (options.direction) {
			control.dir = options.direction;
		} else if (options.type !== 'select' && options.type !== 'checkbox') {
			control.dir = 'auto';
		}
		if (options.disabled) {
			control.disabled = true;
		}
		var eventName = options.type === 'select' || options.type === 'checkbox' ? 'change' : 'input';
		if (typeof options.onChange === 'function') {
			control.addEventListener(eventName, function () {
				state.dirty = true;
				invalidateEvidenceAttestation(state);
				options.onChange(options.type === 'checkbox' ? control.checked : control.value, control);
			});
		}
		if (options.type === 'checkbox') {
			wrap.classList.add('is-checkbox');
			wrap.replaceChildren(control, label);
		} else {
			wrap.appendChild(control);
		}
		if (options.help) {
			var help = element('small', 'tra-vel-proposal-field__help', options.help);
			help.id = id + '-help';
			control.setAttribute('aria-describedby', help.id);
			wrap.appendChild(help);
		}
		parent.appendChild(wrap);
		return control;
	}

	function button(label, className, handler) {
		var control = element('button', className || 'button', label);
		control.type = 'button';
		control.addEventListener('click', handler);
		return control;
	}

	function options(values) {
		return (values || []).map(function (value) {
			return {value: value, label: humanize(value)};
		});
	}

	function isPublicSourceType(sourceType) {
		return ['public_supplier_page', 'official_information'].indexOf(sourceType) !== -1;
	}

	function publicSourceProviders(config, sourceType) {
		var registry = config && config.proposal && plainObject(config.proposal.publicSourceProviders)
			? config.proposal.publicSourceProviders
			: {};
		return Object.keys(registry).filter(function (providerCode) {
			var definition = registry[providerCode];
			return /^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(providerCode)
				&& plainObject(definition)
				&& Array.isArray(definition.sourceTypes)
				&& definition.sourceTypes.indexOf(sourceType) !== -1
				&& Array.isArray(definition.relationships);
		}).sort();
	}

	function publicSourceRelationships(config, sourceType, providerCode) {
		var registry = config && config.proposal && plainObject(config.proposal.publicSourceProviders)
			? config.proposal.publicSourceProviders
			: {};
		var definition = publicSourceProviders(config, sourceType).indexOf(providerCode) !== -1 ? registry[providerCode] : null;
		return definition ? definition.relationships.filter(function (relationship, index, values) {
			return relationship === 'public_reference' && values.indexOf(relationship) === index;
		}) : [];
	}

	function reconcileSourceProviderPolicy(source, config) {
		if (!source || !isPublicSourceType(source.source_type)) {
			if (source) {
				source.source_url = '';
				source.relationship = 'operator_attested';
			}
			return source;
		}
		var providers = publicSourceProviders(config, source.source_type);
		if (providers.indexOf(source.provider_code) === -1) {
			source.provider_code = '';
		}
		var relationships = publicSourceRelationships(config, source.source_type, source.provider_code);
		if (relationships.indexOf(source.relationship) === -1) {
			source.relationship = '';
		}
		source.source_reference = '';
		return source;
	}

	function canCompose(state) {
		if (state.accessMoved || !state.config.canPublish || !state.config.proposalReady) {
			return false;
		}
		if (['queued', 'in_review', 'needs_information', 'ready_for_assistance'].indexOf(String(state.item.status || '')) === -1) {
			return false;
		}
		if (state.config.canOverrideAssignment) {
			return true;
		}
		return Number(state.item.assigned_user_id || 0) > 0
			&& Number(state.item.assigned_user_id) === Number(state.config.currentUserId || 0);
	}

	function renderCaseContext(parent, state) {
		var summary = caseSummary(state.item);
		var context = element('aside', 'tra-vel-proposal-context');
		context.appendChild(element('span', 'tra-vel-proposal-kicker', caseReference(state.item)));
		context.appendChild(element('h3', '', summary.title || 'Structured traveler request'));
		var facts = element('dl', 'tra-vel-proposal-facts');
		var entries = [
			['Origin', summary.origin || '-'],
			['Destinations', Array.isArray(summary.destinations) && summary.destinations.length ? summary.destinations.join(', ') : '-'],
			['Dates', summary.date_text || '-'],
			['Travelers', summary.travelers ? String(Number(summary.travelers.adults || 0) + Number(summary.travelers.children || 0)) : '-'],
			['Budget', summary.budget && summary.budget.amount !== null ? summary.budget.currency + ' ' + summary.budget.amount : 'Not specified'],
			['Scope', Array.isArray(summary.scope) && summary.scope.length ? summary.scope.map(humanize).join(', ') : '-'],
		];
		entries.forEach(function (entry) {
			facts.appendChild(element('dt', '', entry[0]));
			var value = element('dd', '', entry[1]);
			value.dir = 'auto';
			facts.appendChild(value);
		});
		context.appendChild(facts);
		parent.appendChild(context);
	}

	function renderHistory(parent, state) {
		var section = element('section', 'tra-vel-proposal-history');
		var head = element('header', 'tra-vel-proposal-section-head');
		head.appendChild(element('h3', '', 'Proposal history'));
		head.appendChild(element('span', 'tra-vel-proposal-count', String(state.proposals.length)));
		section.appendChild(head);
		if (state.loading) {
			section.appendChild(element('p', 'tra-vel-proposal-muted', text(state.config, 'loading', 'Loading proposal history...')));
		} else if (!state.proposals.length) {
			section.appendChild(element('p', 'tra-vel-proposal-muted', text(state.config, 'noProposals', 'No proposal has been published for this case yet.')));
		} else {
			var list = element('div', 'tra-vel-proposal-history__list');
			state.proposals.forEach(function (proposal) {
				var card = element('article', 'tra-vel-proposal-history-card');
				var title = element('div');
				title.appendChild(element('strong', '', proposal.title || proposal.reference || 'Proposal'));
				var meta = element('small', '', [proposal.reference, humanize(proposal.status), formatDate(proposal.expires_at)].filter(Boolean).join(' | '));
				meta.dir = 'auto';
				title.appendChild(meta);
				card.appendChild(title);
				if (proposal.ledger && proposal.ledger.priced_component_count > 0) {
					card.appendChild(element('bdi', 'tra-vel-proposal-history-card__price', formatMoney(Number(proposal.ledger.priced_total_minor), proposal.ledger.currency)));
				} else {
					card.appendChild(element('span', 'tra-vel-proposal-history-card__price is-pending', 'Final quote'));
				}
				if (canCompose(state) && proposal.status === 'available') {
					var actions = element('div', 'tra-vel-proposal-history-card__actions');
					if (state.confirmWithdraw === proposal.proposal_id) {
						actions.appendChild(element('span', '', 'Withdraw this traveler-visible option?'));
						var confirm = button('Confirm withdrawal', 'button button-secondary', function () {
							withdrawProposal(state, proposal);
						});
						confirm.disabled = state.submitting;
						actions.appendChild(confirm);
						actions.appendChild(button('Keep it', 'button button-link', function () {
							state.confirmWithdraw = '';
							render(state);
						}));
					} else {
						actions.appendChild(button('Revise with fresh evidence', 'button button-secondary', function () {
							state.draft = draftFromProposal(state.item, proposal, state.config);
							state.editing = {proposal_id: proposal.proposal_id, expected_version: Number(proposal.version), reference: proposal.reference || ''};
							state.authoringContext = caseAuthoringContext(state.item);
							state.evidenceAttestation = null;
							state.pending = null;
							state.conflict = false;
							state.dirty = false;
							state.notice = {type: 'info', message: 'Editing a new immutable revision. Recheck every source before publication.'};
							state.focusEditor = true;
							render(state);
						}));
						actions.appendChild(button(text(state.config, 'withdraw', 'Withdraw proposal'), 'button button-link-delete', function () {
							state.confirmWithdraw = proposal.proposal_id;
							render(state);
						}));
					}
					card.appendChild(actions);
				}
				list.appendChild(card);
			});
			section.appendChild(list);
		}
		parent.appendChild(section);
	}

	function renderDirection(parent, state) {
		var section = element('fieldset', 'tra-vel-proposal-section');
		section.appendChild(element('legend', '', '1. Proposal direction and route'));
		var grid = element('div', 'tra-vel-proposal-grid');
		field(grid, state, {id: 'position', label: 'Option position', type: 'select', value: state.draft.position, disabled: Boolean(state.editing), help: state.editing ? 'Position is immutable across revisions.' : '', options: options(state.config.proposal.positions), onChange: function (value) { state.draft.position = value; }});
		field(grid, state, {id: 'currency', label: 'Single proposal currency', type: 'select', value: state.draft.currency, options: options(state.config.proposal.currencies), onChange: function (value) { state.draft.currency = value; invalidateAllComponentSources(state); }});
		field(grid, state, {id: 'title', label: 'Traveler-facing title', value: state.draft.title, required: true, maxLength: 160, wide: true, onChange: function (value) { state.draft.title = value; }});
		field(grid, state, {id: 'summary', label: 'Traveler-facing summary', type: 'textarea', value: state.draft.summary, required: true, maxLength: 800, wide: true, onChange: function (value) { state.draft.summary = value; }});
		field(grid, state, {id: 'why', label: 'Why it fits (one reason per line)', type: 'textarea', value: state.draft.why_it_fits, required: true, maxLength: 1450, onChange: function (value) { state.draft.why_it_fits = value; }});
		field(grid, state, {id: 'tradeoffs', label: 'Trade-offs (one per line)', type: 'textarea', value: state.draft.trade_offs, required: true, maxLength: 1450, onChange: function (value) { state.draft.trade_offs = value; }});
		field(grid, state, {id: 'origin', label: 'Origin', value: state.draft.origin, required: true, maxLength: 120, onChange: function (value) { state.draft.origin = value; }});
		field(grid, state, {id: 'destinations', label: 'Destinations (comma or new line)', value: state.draft.destinations, required: true, maxLength: 700, onChange: function (value) { state.draft.destinations = value; }});
		section.appendChild(grid);
		var legs = element('div', 'tra-vel-proposal-route-legs');
		legs.appendChild(element('h4', '', 'Ordered route legs'));
		legs.appendChild(element('p', 'tra-vel-proposal-section__intro', 'Keep each transport leg visible and editable. Hidden routing data is never carried into a revision.'));
		state.draft.route_legs.forEach(function (leg, index) {
			var card = element('fieldset', 'tra-vel-proposal-repeat-card is-compact');
			card.appendChild(element('legend', '', 'Route leg ' + (index + 1)));
			var legGrid = element('div', 'tra-vel-proposal-grid');
			field(legGrid, state, {id: 'leg-' + index + '-sequence', label: 'Sequence', type: 'number', value: leg.sequence, required: true, min: 1, max: 12, direction: 'ltr', onChange: function (value) { leg.sequence = Number(value); }});
			field(legGrid, state, {id: 'leg-' + index + '-from', label: 'From', value: leg.from, required: true, maxLength: 120, onChange: function (value) { leg.from = value; }});
			field(legGrid, state, {id: 'leg-' + index + '-to', label: 'To', value: leg.to, required: true, maxLength: 120, onChange: function (value) { leg.to = value; }});
			field(legGrid, state, {id: 'leg-' + index + '-mode', label: 'Travel mode', type: 'select', value: leg.mode, options: options(['flight', 'rail', 'road', 'ferry', 'walk', 'other']), onChange: function (value) { leg.mode = value; }});
			card.appendChild(legGrid);
			card.appendChild(button('Remove route leg', 'button button-link-delete', function () {
				state.draft.route_legs.splice(index, 1);
				state.dirty = true;
				render(state);
			}));
			legs.appendChild(card);
		});
		if (state.draft.route_legs.length < 12) {
			legs.appendChild(button('Add route leg', 'button button-secondary', function () {
				var destinations = splitList(state.draft.destinations);
				var previous = state.draft.route_legs[state.draft.route_legs.length - 1];
				state.draft.route_legs.push({
					sequence: state.draft.route_legs.length + 1,
					from: previous ? previous.to : state.draft.origin,
					to: destinations[state.draft.route_legs.length] || destinations[0] || '',
					mode: 'flight',
				});
				state.dirty = true;
				render(state);
			}));
		}
		section.appendChild(legs);
		parent.appendChild(section);
	}

	function renderSources(parent, state) {
		var section = element('fieldset', 'tra-vel-proposal-section');
		section.appendChild(element('legend', '', '2. Operator-attested evidence sources'));
		section.appendChild(element('p', 'tra-vel-proposal-section__intro', 'Cite the evidence you personally checked for this non-binding option. Use an opaque supplier reference or a public HTTPS page. Credentials, query strings, raw prompts, and private notes do not belong here.'));
		state.draft.sources.forEach(function (source, index) {
			var policyBeforeRender = [source.provider_code, source.relationship, source.source_reference, source.source_url].join('\u0000');
			reconcileSourceProviderPolicy(source, state.config);
			if (policyBeforeRender !== [source.provider_code, source.relationship, source.source_reference, source.source_url].join('\u0000')) {
				source.revalidated_now = false;
				invalidateEvidenceAttestation(state);
				state.dirty = true;
			}
			function updateSource(key, value) {
				source[key] = value;
				source.revalidated_now = false;
				var attestation = state.root.querySelector('[name="source-' + index + '-revalidated"]');
				if (attestation) {
					attestation.checked = false;
				}
			}
			var card = element('fieldset', 'tra-vel-proposal-repeat-card');
			card.appendChild(element('legend', '', 'Evidence ' + (index + 1)));
			var grid = element('div', 'tra-vel-proposal-grid');
			var publicSource = isPublicSourceType(source.source_type);
			field(grid, state, {id: 'source-' + index + '-type', label: 'Source type', type: 'select', value: source.source_type, options: options(state.config.proposal.sourceTypes), onChange: function (value) {
				updateSource('source_type', value);
				reconcileSourceProviderPolicy(source, state.config);
				var maximum = Number(state.config.proposal.sourceTtlMinutes[value] || 60);
				if (Number(source.freshness_minutes) > maximum) { source.freshness_minutes = maximum; }
				render(state);
			}});
			if (publicSource) {
				var providerOptions = [{value: '', label: 'Select a registered public provider'}].concat(publicSourceProviders(state.config, source.source_type).map(function (providerCode) {
					return {value: providerCode, label: humanize(providerCode)};
				}));
				field(grid, state, {id: 'source-' + index + '-provider', label: 'Registered public provider', type: 'select', value: source.provider_code, required: true, options: providerOptions, onChange: function (value) {
					updateSource('provider_code', value);
					reconcileSourceProviderPolicy(source, state.config);
					render(state);
				}});
				var relationshipOptions = [{value: '', label: 'Select an allowed relationship'}].concat(options(publicSourceRelationships(state.config, source.source_type, source.provider_code)));
				field(grid, state, {id: 'source-' + index + '-relationship', label: 'Registered relationship', type: 'select', value: source.relationship, required: true, options: relationshipOptions, onChange: function (value) { updateSource('relationship', value); }});
			} else {
				field(grid, state, {id: 'source-' + index + '-relationship', label: 'Evidence relationship', type: 'select', value: 'operator_attested', disabled: true, options: [{value: 'operator_attested', label: 'Operator attested'}], help: 'This records a human evidence check. It does not claim a supplier contract, affiliation, or live integration.'});
				field(grid, state, {id: 'source-' + index + '-provider', label: 'Provider code', value: source.provider_code, required: true, maxLength: 64, direction: 'ltr', pattern: '[a-z0-9]+(?:[-_][a-z0-9]+)*', help: 'Private and connected evidence keeps an opaque provider code; no credentials or private URL are stored.', onChange: function (value) { updateSource('provider_code', stableKey(value, 'source')); }});
			}
			field(grid, state, {id: 'source-' + index + '-label', label: 'Public evidence label', value: source.public_label, required: true, maxLength: 190, onChange: function (value) { updateSource('public_label', value); }});
			field(grid, state, {id: 'source-' + index + '-supplier', label: 'Supplier name (if public)', value: source.supplier_name, maxLength: 190, onChange: function (value) { updateSource('supplier_name', value); }});
			field(grid, state, {id: 'source-' + index + '-seller', label: 'Seller name (if different)', value: source.seller_name, maxLength: 190, onChange: function (value) { updateSource('seller_name', value); }});
			field(grid, state, {id: 'source-' + index + '-reference', label: 'Opaque source reference', value: source.source_reference, required: !publicSource, maxLength: 190, direction: 'ltr', disabled: publicSource, placeholder: 'QUOTE:SUPPLIER-123', onChange: function (value) { updateSource('source_reference', value.trim()); }});
			field(grid, state, {id: 'source-' + index + '-url', label: 'Credential-free HTTPS source URL', type: 'url', value: source.source_url, required: publicSource, disabled: !publicSource, maxLength: 500, direction: 'ltr', placeholder: 'https://provider.example/path', help: publicSource ? 'The server verifies this URL against the selected provider hostname. Query strings and fragments are rejected.' : 'Private evidence uses only the opaque source reference above.', onChange: function (value) { updateSource('source_url', value.trim()); }});
			field(grid, state, {id: 'source-' + index + '-freshness', label: 'Recheck window (minutes)', type: 'number', value: source.freshness_minutes, required: true, min: 16, max: Number(state.config.proposal.sourceTtlMinutes[source.source_type] || 60), step: '1', direction: 'ltr', help: 'This is a revalidation deadline, never an inventory hold.', onChange: function (value) { updateSource('freshness_minutes', Number(value)); }});
			field(grid, state, {id: 'source-' + index + '-revalidated', label: 'I personally rechecked this exact evidence now', type: 'checkbox', value: source.revalidated_now, required: true, wide: true, help: 'This is an operator attestation, not an automated provider verification or inventory hold. Changing evidence or a cited commercial component clears it.', onChange: function (value) { source.revalidated_now = value; }});
			card.appendChild(grid);
			if (state.draft.sources.length > 1) {
				card.appendChild(button('Remove evidence', 'button button-link-delete', function () {
					if (removeSourceAt(state, index)) {
						state.dirty = true;
					}
					render(state);
				}));
			}
			section.appendChild(card);
		});
		if (state.draft.sources.length < 32) {
			section.appendChild(button('Add evidence source', 'button button-secondary', function () {
				state.draft.sources.push({provider_code: 'supplier-source-' + (state.draft.sources.length + 1), source_type: 'supplier_written_quote', relationship: 'operator_attested', public_label: '', supplier_name: '', seller_name: 'Tra-Vel', source_reference: '', source_url: '', freshness_minutes: 60, revalidated_now: false});
				state.dirty = true;
				render(state);
			}));
		}
		parent.appendChild(section);
	}

	function renderComponents(parent, state) {
		var section = element('fieldset', 'tra-vel-proposal-section');
		section.appendChild(element('legend', '', '3. Trip components and evidence-matched prices'));
		section.appendChild(element('p', 'tra-vel-proposal-section__intro', 'Leave a component unpriced until an operator has matched its full-party total to the cited evidence. This remains a non-binding planning amount until final revalidation and quote.'));
		state.draft.components.forEach(function (component, index) {
			function updateComponent(key, value, rerender) {
				component[key] = value;
				invalidateComponentSources(state, component);
				if (rerender) {
					render(state);
				}
			}
			var card = element('fieldset', 'tra-vel-proposal-repeat-card');
			card.appendChild(element('legend', '', 'Component ' + (index + 1)));
			var grid = element('div', 'tra-vel-proposal-grid');
			field(grid, state, {id: 'component-' + index + '-category', label: 'Category', type: 'select', value: component.category, options: options(state.config.proposal.categories), onChange: function (value) { updateComponent('category', value); }});
			field(grid, state, {id: 'component-' + index + '-key', label: 'Stable component key', value: component.component_key, required: true, maxLength: 64, direction: 'ltr', pattern: '[a-z0-9]+(?:[-_][a-z0-9]+)*', onChange: function (value) { updateComponent('component_key', stableKey(value, 'component-' + (index + 1))); }});
			field(grid, state, {id: 'component-' + index + '-title', label: 'Traveler-facing title', value: component.title, required: true, maxLength: 200, onChange: function (value) { updateComponent('title', value); }});
			var evidence = element('fieldset', 'tra-vel-proposal-source-checklist is-wide');
			evidence.appendChild(element('legend', '', 'Evidence used'));
			state.draft.sources.forEach(function (source, sourceIndex) {
				var option = element('label', 'tra-vel-proposal-source-checklist__option');
				var checkbox = element('input');
				checkbox.type = 'checkbox';
				checkbox.name = 'component-' + index + '-source-' + sourceIndex;
				checkbox.checked = (component.source_indexes || []).map(Number).indexOf(sourceIndex) !== -1;
				checkbox.addEventListener('change', function () {
					var previous = (component.source_indexes || []).map(Number);
					var selected = previous.filter(function (value) { return value !== sourceIndex; });
					if (checkbox.checked) {
						selected.push(sourceIndex);
					}
					component.source_indexes = selected.sort(function (left, right) { return left - right; });
					invalidateComponentSources(state, component, previous);
					state.dirty = true;
				});
				option.appendChild(checkbox);
				option.appendChild(element('span', '', (source.public_label || 'Evidence') + ' #' + (sourceIndex + 1)));
				evidence.appendChild(option);
			});
			grid.appendChild(evidence);
			field(grid, state, {id: 'component-' + index + '-description', label: 'What the traveler receives', type: 'textarea', value: component.description, required: true, maxLength: 800, wide: true, onChange: function (value) { updateComponent('description', value); }});
			field(grid, state, {id: 'component-' + index + '-priced', label: 'I matched this full-party total to the cited evidence', type: 'checkbox', value: component.priced, wide: true, help: 'Operator-attested planning amount; final price, availability, and terms still require a personal quote.', onChange: function (value) { updateComponent('priced', value, true); }});
			field(grid, state, {id: 'component-' + index + '-amount', label: 'Full-party total (' + state.draft.currency + ')', value: component.amount_major, required: component.priced, disabled: !component.priced, direction: 'ltr', inputMode: 'decimal', pattern: '\\d{1,10}(?:[.,]\\d{1,2})?', placeholder: '1250.00', help: 'Major units; the server stores exact integer minor units.', onChange: function (value) { updateComponent('amount_major', value); }});
			field(grid, state, {id: 'component-' + index + '-basis', label: 'Price basis', type: 'select', value: component.basis, disabled: !component.priced, options: options(['trip_total', 'stay_total', 'ticket_total', 'activity_total', 'item_total']), onChange: function (value) { updateComponent('basis', value); }});
			field(grid, state, {id: 'component-' + index + '-taxes', label: 'Taxes', type: 'select', value: component.taxes, disabled: !component.priced, options: options(['included', 'excluded', 'unknown']), onChange: function (value) { updateComponent('taxes', value); }});
			field(grid, state, {id: 'component-' + index + '-fees', label: 'Fees', type: 'select', value: component.fees, disabled: !component.priced, options: options(['included', 'excluded', 'unknown']), onChange: function (value) { updateComponent('fees', value); }});
			field(grid, state, {id: 'component-' + index + '-cancellation', label: 'Cancellation conditions', type: 'textarea', value: component.cancellation, required: true, maxLength: 500, onChange: function (value) { updateComponent('cancellation', value); }});
			field(grid, state, {id: 'component-' + index + '-changes', label: 'Change conditions', type: 'textarea', value: component.changes, required: true, maxLength: 500, onChange: function (value) { updateComponent('changes', value); }});
			field(grid, state, {id: 'component-' + index + '-inclusions', label: 'Baggage or inclusions', type: 'textarea', value: component.inclusions, required: true, maxLength: 500, wide: true, onChange: function (value) { updateComponent('inclusions', value); }});
			card.appendChild(grid);
			if (state.draft.components.length > 1) {
				card.appendChild(button('Remove component', 'button button-link-delete', function () {
					state.draft.components.splice(index, 1);
					state.dirty = true;
					render(state);
				}));
			}
			section.appendChild(card);
		});
		if (state.draft.components.length < 16) {
			section.appendChild(button('Add trip component', 'button button-secondary', function () {
				var count = state.draft.components.length + 1;
				state.draft.components.push({component_key: 'trip-component-' + count, category: 'activities', title: '', description: '', priced: false, amount_major: '', basis: 'activity_total', taxes: 'unknown', fees: 'unknown', cancellation: 'תנאי הביטול יאושרו בהצעת המחיר הסופית.', changes: 'אפשרויות שינוי יאושרו בהצעת המחיר הסופית.', inclusions: 'התכולה המדויקת תאושר בהצעת המחיר הסופית.', source_indexes: [0]});
				state.dirty = true;
				render(state);
			}));
		}
		parent.appendChild(section);
	}

	function renderItinerary(parent, state) {
		var section = element('fieldset', 'tra-vel-proposal-section');
		section.appendChild(element('legend', '', '4. Editable itinerary'));
		state.draft.itinerary.forEach(function (day, index) {
			var card = element('fieldset', 'tra-vel-proposal-repeat-card is-compact');
			card.appendChild(element('legend', '', 'Day ' + day.day));
			var grid = element('div', 'tra-vel-proposal-grid');
			field(grid, state, {id: 'day-' + index + '-number', label: 'Day number', type: 'number', value: day.day, required: true, min: 1, max: 365, direction: 'ltr', onChange: function (value) { day.day = Number(value); }});
			field(grid, state, {id: 'day-' + index + '-place', label: 'Place', value: day.place, required: true, maxLength: 120, onChange: function (value) { day.place = value; }});
			field(grid, state, {id: 'day-' + index + '-title', label: 'Day title', value: day.title, required: true, maxLength: 200, onChange: function (value) { day.title = value; }});
			field(grid, state, {id: 'day-' + index + '-components', label: 'Component keys (comma separated)', value: day.component_keys, maxLength: 800, direction: 'ltr', help: 'Use the stable keys from the components above.', onChange: function (value) { day.component_keys = value; }});
			card.appendChild(grid);
			if (state.draft.itinerary.length > 1) {
				card.appendChild(button('Remove day', 'button button-link-delete', function () { state.draft.itinerary.splice(index, 1); state.dirty = true; render(state); }));
			}
			section.appendChild(card);
		});
		if (state.draft.itinerary.length < 31) {
			section.appendChild(button('Add itinerary day', 'button button-secondary', function () {
				var next = state.draft.itinerary.reduce(function (maximum, day) { return Math.max(maximum, Number(day.day || 0)); }, 0) + 1;
				state.draft.itinerary.push({day: next, place: splitList(state.draft.destinations)[0] || '', title: '', component_keys: ''});
				state.dirty = true;
				render(state);
			}));
		}
		state.draft.extra_gaps.forEach(function (gap, index) {
			var gapGrid = element('fieldset', 'tra-vel-proposal-grid tra-vel-proposal-extra-gap');
			gapGrid.appendChild(element('legend', '', 'Additional review gap ' + (index + 1)));
			field(gapGrid, state, {id: 'extra-gap-' + index + '-code', label: 'Gap type', type: 'select', value: gap.code, required: true, options: options(['policy_revalidation', 'schedule_revalidation', 'other']), onChange: function (value) { gap.code = value; }});
			field(gapGrid, state, {id: 'extra-gap-' + index + '-label', label: 'Traveler-facing gap explanation', value: gap.label, required: true, maxLength: 240, onChange: function (value) { gap.label = value; }});
			gapGrid.appendChild(button('Remove review gap', 'button button-link-delete', function () { state.draft.extra_gaps.splice(index, 1); state.dirty = true; render(state); }));
			section.appendChild(gapGrid);
		});
		if (state.draft.extra_gaps.length < 12) {
			section.appendChild(button('Add review gap', 'button button-secondary', function () { state.draft.extra_gaps.push({code: 'other', label: ''}); state.dirty = true; render(state); }));
		}
		parent.appendChild(section);
	}

	function buildComposition(state, requireAttestation) {
		var destinations = splitList(state.draft.destinations);
		var reasons = splitLines(state.draft.why_it_fits);
		var tradeoffs = splitLines(state.draft.trade_offs);
		if (!destinations.length || !reasons.length || !tradeoffs.length) {
			throw new Error('Add at least one destination, fit reason, and trade-off.');
		}
		if (!state.draft.sources.every(function (source) { return source.revalidated_now === true; })) {
			throw new Error('Recheck and attest every exact evidence source after its final edit.');
		}
		var sources = state.draft.sources.map(function (source) {
			return {
				provider_code: stableKey(source.provider_code, 'source'),
				source_type: source.source_type,
				relationship: source.relationship,
				public_label: String(source.public_label || '').trim(),
				supplier_name: String(source.supplier_name || '').trim(),
				seller_name: String(source.seller_name || '').trim(),
				source_reference: String(source.source_reference || '').trim(),
				source_url: String(source.source_url || '').trim(),
				freshness_minutes: Number(source.freshness_minutes),
				revalidated_now: true,
			};
		});
		var components = state.draft.components.map(function (component, index) {
			var amount = component.priced ? majorToMinor(component.amount_major) : null;
			var sourceIndexes = Array.from(new Set((component.source_indexes || []).map(Number))).sort(function (left, right) { return left - right; });
			if (component.priced && amount === null) {
				throw new Error('Component ' + (index + 1) + ' needs a valid sourced full-party total with no more than two decimals.');
			}
			if (!sourceIndexes.length || sourceIndexes.length > 8 || sourceIndexes.some(function (sourceIndex) { return !Number.isInteger(sourceIndex) || sourceIndex < 0 || sourceIndex >= sources.length; })) {
				throw new Error('Component ' + (index + 1) + ' must cite between one and eight available evidence sources.');
			}
			return {
				component_key: stableKey(component.component_key, 'component-' + (index + 1)),
				category: component.category,
				title: String(component.title || '').trim(),
				description: String(component.description || '').trim(),
				price: component.priced ? {
					priced: true,
					total_for_party_minor: amount,
					currency: state.draft.currency,
					basis: component.basis,
					taxes: component.taxes,
					fees: component.fees,
				} : {
					priced: false,
					total_for_party_minor: null,
					currency: null,
					basis: 'not_priced',
					taxes: 'unknown',
					fees: 'unknown',
				},
				conditions: {
					cancellation: String(component.cancellation || '').trim(),
					changes: String(component.changes || '').trim(),
					baggage_or_inclusions: String(component.inclusions || '').trim(),
				},
				source_indexes: sourceIndexes,
			};
		});
		var componentKeys = {};
		components.forEach(function (component) { componentKeys[component.component_key] = true; });
		var itinerary = state.draft.itinerary.map(function (day, index) {
			var keys = splitList(day.component_keys).map(function (key) { return stableKey(key, ''); }).filter(Boolean);
			keys.forEach(function (key) {
				if (!componentKeys[key]) {
					throw new Error('Itinerary day ' + (index + 1) + ' references a component key that is not present.');
				}
			});
			return {day: Number(day.day), place: String(day.place || '').trim(), title: String(day.title || '').trim(), component_keys: keys};
		});
		var unresolved = state.draft.extra_gaps.map(function (gap, index) {
			if (['policy_revalidation', 'schedule_revalidation', 'other'].indexOf(gap.code) === -1 || !String(gap.label || '').trim()) {
				throw new Error('Explain additional review gap ' + (index + 1) + '.');
			}
			return {code: gap.code, label: String(gap.label).trim()};
		});
		var composition = {
			position: state.draft.position,
			title: state.draft.title.trim(),
			summary: state.draft.summary.trim(),
			why_it_fits: reasons,
			trade_offs: tradeoffs,
			route: {
				origin: state.draft.origin.trim(),
				destinations: destinations,
				legs: (Array.isArray(state.draft.route_legs) ? state.draft.route_legs : []).map(function (leg) {
					return {sequence: Number(leg.sequence), from: String(leg.from || '').trim(), to: String(leg.to || '').trim(), mode: leg.mode};
				}),
			},
			itinerary: itinerary,
			components: components,
			sources: sources,
			unresolved_items: unresolved,
		};
		if (!requireAttestation) {
			return composition;
		}
		if (!evidenceAttestationCurrent(state, composition)) {
			state.evidenceAttestation = null;
			throw new Error('Record a fresh final evidence check after the last edit and publish within five minutes.');
		}
		composition.evidence_attestation_token = state.evidenceAttestation.token;
		return composition;
	}

	function evidenceAttestationCurrent(state, unsignedComposition) {
		if (!state || !state.evidenceAttestation || typeof state.evidenceAttestation.token !== 'string') {
			return false;
		}
		var expires = Date.parse(state.evidenceAttestation.expires_at || '');
		if (!Number.isFinite(expires) || expires <= Date.now() + 5000) {
			return false;
		}
		try {
			var composition = unsignedComposition || buildComposition(state, false);
			return state.evidenceAttestation.serialized === JSON.stringify(composition);
		} catch (error) {
			return false;
		}
	}

	function attestationResponse(payload) {
		if (!exactKeys(payload, ['attestation_token', 'checked_at', 'expires_at'])
			|| typeof payload.attestation_token !== 'string' || payload.attestation_token.length < 80 || payload.attestation_token.length > 2048
			|| !Number.isFinite(Date.parse(payload.checked_at || '')) || !Number.isFinite(Date.parse(payload.expires_at || ''))
			|| Date.parse(payload.expires_at) <= Date.now()) {
			var invalid = new Error('The evidence service did not return a valid short-lived attestation. No proposal was published.');
			invalid.code = 'tra_vel_assisted_composition_attestation_invalid';
			throw invalid;
		}
		return payload;
	}

	function reviewTotals(state) {
		var total = 0;
		var count = 0;
		var valid = true;
		state.draft.components.forEach(function (component) {
			if (!component.priced) {
				return;
			}
			var amount = majorToMinor(component.amount_major);
			if (amount === null) {
				valid = false;
				return;
			}
			total += amount;
			count += 1;
		});
		return {total: total, count: count, valid: valid};
	}

	function renderReview(parent, state) {
		var section = element('section', 'tra-vel-proposal-review');
		section.appendChild(element('h3', '', text(state.config, 'finalReview', 'Final review')));
		var totals = reviewTotals(state);
		var metrics = element('div', 'tra-vel-proposal-review__metrics');
		[['Evidence sources', state.draft.sources.length], ['Trip components', state.draft.components.length], ['Priced components', totals.count]].forEach(function (entry) {
			var metric = element('div');
			metric.appendChild(element('strong', '', String(entry[1])));
			metric.appendChild(element('span', '', entry[0]));
			metrics.appendChild(metric);
		});
		if (totals.count && totals.valid) {
			var price = element('div');
			price.appendChild(element('bdi', '', formatMoney(totals.total, state.draft.currency)));
			price.appendChild(element('span', '', 'Operator-attested planning subtotal'));
			metrics.appendChild(price);
		}
		section.appendChild(metrics);
		var boundary = element('div', 'tra-vel-proposal-boundary');
		boundary.appendChild(element('strong', '', 'Non-binding publication boundary'));
		boundary.appendChild(element('p', '', text(state.config, 'priceBoundary', state.config.proposal.disclosure)));
		boundary.appendChild(element('small', '', 'The server generates identities, binds the current request revision, computes the ledger and integrity records, derives expiry, and adds mandatory review gaps. Evidence matching remains operator-attested and every commercial fact still requires final revalidation.'));
		section.appendChild(boundary);
		var attestationReady = evidenceAttestationCurrent(state);
		var attestationPanel = element('div', 'tra-vel-proposal-boundary');
		attestationPanel.appendChild(element('strong', '', attestationReady ? 'Final evidence check recorded' : 'Final evidence check required'));
		attestationPanel.appendChild(element('p', '', attestationReady
			? 'This exact proposal is signed for five minutes. Any edit or expiry requires a new check.'
			: 'After the final edit, attest that you personally rechecked every cited source and the commercial claims it supports. This does not verify supplier inventory or hold a price.'));
		var attest = button(attestationReady ? 'Record a newer evidence check' : 'I rechecked every cited source now', 'button button-secondary', function () {
			attestEvidence(state, parent);
		});
		attest.disabled = state.submitting || state.conflict || state.uncertain;
		attestationPanel.appendChild(attest);
		section.appendChild(attestationPanel);
		var publishLabel = state.editing ? 'Publish revised non-binding proposal' : text(state.config, 'publish', 'Publish non-binding proposal');
		var submit = element('button', 'button button-primary button-hero tra-vel-proposal-publish', state.submitting ? text(state.config, 'publishing', 'Publishing...') : publishLabel);
		submit.type = 'submit';
		submit.disabled = state.submitting || state.conflict || !attestationReady;
		if (state.submitting) {
			submit.classList.add('is-running');
		}
		section.appendChild(submit);
		parent.appendChild(section);
	}

	function renderNotice(parent, state) {
		if (!state.notice || !state.notice.message) {
			return;
		}
		var notice = element('div', 'tra-vel-proposal-notice is-' + (state.notice.type || 'info'));
		notice.setAttribute('role', state.notice.type === 'error' ? 'alert' : 'status');
		notice.tabIndex = -1;
		notice.appendChild(element('strong', '', state.uncertain ? 'Proposal outcome not yet confirmed' : (state.notice.type === 'error' ? 'Proposal not published' : 'Proposal update')));
		notice.appendChild(element('p', '', state.notice.message));
		if (state.notice.code) {
			var code = element('code', '', state.notice.code);
			code.dir = 'ltr';
			notice.appendChild(code);
		}
		if (state.conflict) {
			var refresh = button('Load latest case and proposal state', 'button button-secondary', function () {
				refreshConflict(state);
			});
			refresh.disabled = state.submitting;
			notice.appendChild(refresh);
		}
		if (state.uncertain) {
			var retry = button('Retry the exact protected request', 'button button-primary', function () {
				retryUncertainMutation(state);
			});
			retry.dataset.traVelProposalUncertainRetry = 'true';
			retry.disabled = state.submitting;
			notice.appendChild(retry);
			notice.appendChild(element('small', '', 'Editing and alternative mutations stay locked until this exact request is reconciled, preventing a duplicate option.'));
		}
		parent.appendChild(notice);
	}

	function render(state) {
		state.root.replaceChildren();
		var workspace = element('section', 'tra-vel-proposal-workspace');
		workspace.id = 'tra-vel-proposal-workspace-' + caseId(state.item).replace(/[^a-z0-9]/gi, '');
		workspace.setAttribute('aria-labelledby', fieldId(state, 'heading'));
		workspace.setAttribute('aria-busy', state.submitting ? 'true' : 'false');
		var heading = element('header', 'tra-vel-proposal-workspace__heading');
		var copy = element('div');
		copy.appendChild(element('span', 'tra-vel-proposal-kicker', 'Assisted proposal composer'));
		var title = element('h2', '', text(state.config, 'heading', 'Build a sourced trip proposal'));
		title.id = fieldId(state, 'heading');
		title.tabIndex = -1;
		copy.appendChild(title);
		copy.appendChild(element('p', '', text(state.config, 'intro', 'Prepare one editable option with evidence for every commercial fact.')));
		heading.appendChild(copy);
		var close = button(text(state.config, 'close', 'Close proposal workspace'), 'button tra-vel-proposal-close', function () {
			if (state.options && typeof state.options.onClose === 'function') {
				state.options.onClose();
			}
		});
		heading.appendChild(close);
		workspace.appendChild(heading);
		renderNotice(workspace, state);
		var layout = element('div', 'tra-vel-proposal-workspace__layout');
		renderCaseContext(layout, state);
		var main = element('div', 'tra-vel-proposal-main');
		renderHistory(main, state);
		if (!state.config.proposalReady) {
			main.appendChild(element('div', 'notice notice-warning inline', text(state.config, 'storeUnavailable', 'Proposal tools are unavailable until storage is ready.')));
		} else if (!canCompose(state)) {
			var active = ['queued', 'in_review', 'needs_information', 'ready_for_assistance'].indexOf(String(state.item.status || '')) !== -1;
			main.appendChild(element('div', 'notice notice-info inline', state.accessMoved
				? 'This case moved to another operator after the protected request was confirmed. Reopen it from the current queue if access is assigned again.'
				: active
				? text(state.config, 'assignmentRequired', 'Claim this case before publishing a proposal.')
				: 'This case is historical. Its proposals remain readable, but no new proposal can be published.'));
		} else {
			if (state.editing) {
				var revisionBanner = element('div', 'tra-vel-proposal-revision-banner');
				var revisionCopy = element('div');
				revisionCopy.appendChild(element('strong', '', 'Revising ' + (state.editing.reference || 'proposal')));
				revisionCopy.appendChild(element('p', '', 'A fresh immutable revision will replace the traveler-visible version only after every source passes revalidation.'));
				revisionBanner.appendChild(revisionCopy);
				revisionBanner.appendChild(button('Cancel revision', 'button button-secondary', function () {
					if (state.dirty) {
						if (!retainDraft(state, state.draft, 'Revision cancelled by the operator')) {
							state.notice = {type: 'error', message: 'Three authored copies are already retained. Use or explicitly discard one before cancelling this revision; no copy was removed.'};
							state.noticeFocus = true;
							render(state);
							return;
						}
					}
					state.editing = null;
					state.pending = null;
					state.conflict = false;
					state.draft = defaultDraft(state.item);
					state.authoringContext = caseAuthoringContext(state.item);
					state.evidenceAttestation = null;
					state.dirty = false;
					state.notice = state.retainedDrafts.length ? {type: 'info', message: 'Revision editing stopped. Your authored fields are retained as a possible new option.'} : null;
					render(state);
				}));
				main.appendChild(revisionBanner);
			}
			if (state.retainedDrafts.length) {
				var retained = element('div', 'tra-vel-proposal-revision-banner is-retained');
				var retainedCopy = element('div');
				retainedCopy.appendChild(element('strong', '', state.retainedDrafts.length + ' authored draft ' + (state.retainedDrafts.length === 1 ? 'copy is' : 'copies are') + ' retained'));
				retainedCopy.appendChild(element('p', '', state.editing
					? 'The active revision starts from the latest server state. The most recent retained copy can become a separate option after review against the refreshed case.'
					: 'Up to three prior editor copies remain available as separate option drafts until you use or discard them.'));
				retained.appendChild(retainedCopy);
				var retainedActions = element('div', 'tra-vel-proposal-history-card__actions');
				retainedActions.appendChild(button('Use most recent retained copy', 'button button-secondary', function () {
					state.draft = state.retainedDrafts.shift().draft;
					state.authoringContext = caseAuthoringContext(state.item);
					state.retentionCapacityReached = false;
					state.editing = null;
					state.pending = null;
					state.conflict = false;
					state.evidenceAttestation = null;
					state.dirty = true;
					state.notice = {type: 'info', message: 'The retained fields are now a new option draft. Recheck every source and review the refreshed case before publication.'};
					render(state);
				}));
				retainedActions.appendChild(button('Discard most recent copy', 'button button-link-delete', function () {
					state.retainedDrafts.shift();
					state.retentionCapacityReached = false;
					render(state);
				}));
				if (state.retainedDrafts.length > 1) {
					retainedActions.appendChild(button('Discard all retained copies', 'button button-link-delete', function () {
						state.retainedDrafts = [];
						state.retentionCapacityReached = false;
						render(state);
					}));
				}
				retained.appendChild(retainedActions);
				main.appendChild(retained);
			}
			var form = element('form', 'tra-vel-proposal-form');
			form.noValidate = false;
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				publishProposal(state, form);
			});
			renderDirection(form, state);
			renderSources(form, state);
			renderComponents(form, state);
			renderItinerary(form, state);
			renderReview(form, state);
			main.appendChild(form);
		}
		layout.appendChild(main);
		workspace.appendChild(layout);
		if (state.submitting) {
			workspace.querySelectorAll('button, input, select, textarea').forEach(function (control) {
				control.disabled = true;
			});
		} else if (state.uncertain) {
			workspace.querySelectorAll('button, input, select, textarea').forEach(function (control) {
				control.disabled = true;
			});
			close.disabled = false;
			var exactRetry = workspace.querySelector('[data-tra-vel-proposal-uncertain-retry="true"]');
			if (exactRetry) {
				exactRetry.disabled = false;
			}
		}
		state.root.appendChild(workspace);
		if (state.focusHeading) {
			state.focusHeading = false;
			window.requestAnimationFrame(function () { title.focus(); });
		}
		if (state.focusEditor) {
			state.focusEditor = false;
			window.requestAnimationFrame(function () {
				var firstField = state.root.querySelector('[name="title"]');
				if (firstField) { firstField.focus(); }
			});
		}
		if (state.noticeFocus) {
			state.noticeFocus = false;
			window.requestAnimationFrame(function () {
				var notice = state.root.querySelector('.tra-vel-proposal-notice');
				if (notice) { notice.focus(); }
			});
		}
	}

	async function loadProposals(state) {
		state.loading = true;
		render(state);
		try {
			state.proposals = await fetchProposalList(state);
			state.loading = false;
			render(state);
		} catch (error) {
			state.loading = false;
			state.notice = {type: 'error', message: error.message, code: error.code || ''};
			state.noticeFocus = true;
			render(state);
		}
	}

	async function attestEvidence(state, form) {
		if (state.submitting || state.conflict || state.uncertain || !canCompose(state)) {
			return;
		}
		if (!form.checkValidity()) {
			form.reportValidity();
			return;
		}
		var composition;
		try {
			composition = buildComposition(state, false);
		} catch (error) {
			state.notice = {type: 'error', message: error.message};
			state.noticeFocus = true;
			render(state);
			return;
		}
		if (!validAuthoringContext(state.authoringContext)) {
			state.notice = {type: 'error', message: 'Refresh the quote case before recording evidence.'};
			state.noticeFocus = true;
			render(state);
			return;
		}
		var context = cloneDraft(state.authoringContext);
		state.submitting = true;
		state.notice = {type: 'info', message: 'Recording a short-lived operator attestation for this exact evidence and claim set.'};
		render(state);
		try {
			var payload = await request(state.config, evidenceAttestationUrl(state.config, state.item), {
				method: 'POST',
				body: {
					composition: composition,
					expected_case_version: context.expected_case_version,
					expected_case_revision: context.expected_case_revision,
					expected_request_digest: context.expected_request_digest,
				},
			});
			var attestation = attestationResponse(payload);
			state.evidenceAttestation = {
				token: attestation.attestation_token,
				checked_at: attestation.checked_at,
				expires_at: attestation.expires_at,
				serialized: JSON.stringify(composition),
			};
			state.pending = null;
			state.submitting = false;
			state.notice = {type: 'success', message: 'The final evidence check is recorded for five minutes. Publish without changing the proposal, or record a new check after edits.'};
			render(state);
		} catch (error) {
			state.submitting = false;
			state.evidenceAttestation = null;
			state.notice = {type: 'error', message: error.message, code: error.code || ''};
			state.noticeFocus = true;
			render(state);
		}
	}

	async function publishProposal(state, form) {
		if (state.submitting || state.conflict || state.uncertain || !canCompose(state)) {
			return;
		}
		if (!form.checkValidity()) {
			form.reportValidity();
			return;
		}
		var composition;
		try {
			composition = buildComposition(state, true);
		} catch (error) {
			state.notice = {type: 'error', message: error.message};
			state.noticeFocus = true;
			render(state);
			return;
		}
		var expectedVersion = proposalExpectedVersion(state.editing);
		if (!validAuthoringContext(state.authoringContext)) {
			state.notice = {type: 'error', message: 'The quote-case authoring context is incomplete. Refresh the case before publication.'};
			state.noticeFocus = true;
			render(state);
			return;
		}
		var casePrecondition = cloneDraft(state.authoringContext);
		var serialized = JSON.stringify({composition: composition, proposal_id: state.editing ? state.editing.proposal_id : '', expected_version: expectedVersion, case_precondition: casePrecondition});
		if (!state.pending || state.pending.serialized !== serialized) {
			state.pending = {serialized: serialized, key: idempotencyKey('proposal-compose')};
		}
		state.submitting = true;
		state.notice = null;
		render(state);
		try {
			var publicationUrl = compositionUrl(state.config, state.item, state.editing);
			var publicationBody = {
				composition: composition,
				expected_version: expectedVersion,
				expected_case_version: casePrecondition.expected_case_version,
				expected_case_revision: casePrecondition.expected_case_revision,
				expected_request_digest: casePrecondition.expected_request_digest,
				idempotency_key: state.pending.key,
			};
			var payload = await request(state.config, publicationUrl, {
				method: 'POST',
				body: publicationBody,
			});
			var publishedProposal = mutationProposal(payload, {
				case_id: caseId(state.item),
				proposal_id: state.editing ? state.editing.proposal_id : '',
				expected_version: expectedVersion,
				expected_status: 'available',
				expected_case_revision: casePrecondition.expected_case_revision,
				expected_request_digest: casePrecondition.expected_request_digest,
			});
			if (payload.replayed === true) {
				var publicationReconciliation = await reconcileProposalState(state, publishedProposal.proposal_id);
				if (publicationReconciliation.accessMoved) {
					completeAccessMovedReplay(state, 'publish', publishedProposal.proposal_id);
					render(state);
					return;
				}
				publishedProposal = publicationReconciliation.proposal;
			} else {
				state.proposals = mergeProposalMonotonic(state.proposals, publishedProposal);
				publishedProposal = state.proposals.find(function (proposal) { return proposal.proposal_id === publishedProposal.proposal_id; });
			}
			state.submitting = false;
			state.pending = null;
			state.uncertain = null;
			state.conflict = false;
			state.notice = {type: 'success', message: payload.replayed === true
				? 'The protected request was already handled. The latest authoritative proposal state is shown.'
				: text(state.config, 'published', 'The sourced proposal is now available in the traveler workspace.')};
			state.editing = null;
			state.draft = defaultDraft(state.item);
			state.authoringContext = caseAuthoringContext(state.item);
			state.evidenceAttestation = null;
			state.dirty = false;
			render(state);
			if (state.options && typeof state.options.onPublished === 'function') {
				state.options.onPublished(publishedProposal);
			}
		} catch (error) {
			state.submitting = false;
			if (error.outcomeUncertain) {
				state.uncertain = {
					kind: 'publish',
					url: publicationUrl,
					body: cloneDraft(publicationBody),
					proposal_id: state.editing ? state.editing.proposal_id : '',
					validation_context: {
						expected_status: 'available',
						expected_case_revision: casePrecondition.expected_case_revision,
						expected_request_digest: casePrecondition.expected_request_digest,
					},
				};
			}
			var refreshable = [
				'tra_vel_assisted_proposal_version_conflict',
				'tra_vel_assisted_proposal_read_changed',
				'tra_vel_assisted_proposal_parent_changed',
				'tra_vel_assisted_proposal_request_changed',
				'tra_vel_assisted_proposal_case_precondition_failed',
			].indexOf(error.code || '') !== -1;
			state.conflict = Boolean(error.status === 409 && refreshable);
			if (state.conflict && state.editing) {
				retainDraft(state, state.draft, 'Revision conflict before refresh');
			}
			if (error.code === 'tra_vel_assisted_proposal_idempotency_conflict') {
				state.pending = null;
			}
			state.notice = {type: 'error', message: error.message + (state.conflict ? ' Refresh the case state before another publication attempt.' : ''), code: error.code || ''};
			state.noticeFocus = true;
			render(state);
		}
	}

	async function retryUncertainMutation(state) {
		if (!state.uncertain || state.submitting) {
			return;
		}
		var frozen = {
			kind: state.uncertain.kind,
			url: state.uncertain.url,
			body: cloneDraft(state.uncertain.body),
			proposal_id: state.uncertain.proposal_id,
			validation_context: cloneDraft(state.uncertain.validation_context || {}),
		};
		state.submitting = true;
		state.notice = {type: 'info', message: 'Retrying the exact protected request without changing its body or idempotency key.'};
		render(state);
		try {
			var payload = await request(state.config, frozen.url, {method: 'POST', body: frozen.body});
			var retryContext = frozen.validation_context;
			retryContext.case_id = caseId(state.item);
			retryContext.proposal_id = frozen.proposal_id || '';
			retryContext.expected_version = Number(frozen.body.expected_version);
			retryContext.expected_status = retryContext.expected_status || (frozen.kind === 'withdraw' ? 'withdrawn' : 'available');
			var confirmed = mutationProposal(payload, retryContext);
			if (payload.replayed === true) {
				var retryReconciliation = await reconcileProposalState(state, confirmed.proposal_id);
				if (retryReconciliation.accessMoved) {
					completeAccessMovedReplay(state, frozen.kind, confirmed.proposal_id);
					render(state);
					return;
				}
				confirmed = retryReconciliation.proposal;
			} else {
				state.proposals = mergeProposalMonotonic(state.proposals, confirmed);
				confirmed = state.proposals.find(function (proposal) { return proposal.proposal_id === confirmed.proposal_id; });
			}
			state.submitting = false;
			state.uncertain = null;
			state.conflict = false;
			if (frozen.kind === 'publish') {
				state.pending = null;
				state.editing = null;
				state.draft = defaultDraft(state.item);
				state.authoringContext = caseAuthoringContext(state.item);
				state.evidenceAttestation = null;
				state.dirty = false;
				state.notice = {type: 'success', message: 'The exact protected request is reconciled. The latest authoritative proposal state is shown.'};
				if (state.options && typeof state.options.onPublished === 'function') {
					state.options.onPublished(confirmed);
				}
			} else {
				delete state.withdrawPending[frozen.proposal_id];
				state.confirmWithdraw = '';
				state.notice = {type: 'success', message: 'The exact protected withdrawal request is reconciled. The latest authoritative proposal state is shown.'};
			}
			render(state);
		} catch (error) {
			state.submitting = false;
			state.notice = {type: 'error', message: error.message + ' The editor remains locked so a different request cannot create a duplicate.', code: error.code || ''};
			state.noticeFocus = true;
			render(state);
		}
	}

	async function refreshConflict(state) {
		if (!state.conflict || state.submitting) {
			return;
		}
		if (state.retentionCapacityReached) {
			state.notice = {type: 'error', message: 'The latest conflicting draft is still active and was not discarded. Use or explicitly discard one retained copy before loading server state.'};
			state.noticeFocus = true;
			render(state);
			return;
		}
		state.submitting = true;
		render(state);
		try {
			var casePayload = await request(state.config, operatorCaseUrl(state.config, state.item));
			if (!casePayload.case || caseId(casePayload.case) !== caseId(state.item)) {
				throw new Error('The latest quote case could not be verified.');
			}
			state.item = casePayload.case;
			if (state.options && typeof state.options.onCaseUpdated === 'function') {
				state.options.onCaseUpdated(casePayload.case);
			}
			state.proposals = await fetchProposalList(state);
			var latest = state.editing ? state.proposals.find(function (proposal) { return proposal.proposal_id === state.editing.proposal_id; }) : null;
			if (state.editing && latest && latest.status === 'available') {
				state.draft = draftFromProposal(state.item, latest, state.config);
				state.editing.expected_version = Number(latest.version);
				state.authoringContext = caseAuthoringContext(state.item);
				state.evidenceAttestation = null;
				state.dirty = false;
				state.notice = {type: 'info', message: 'The latest immutable proposal is now the revision base. Recheck every source before editing or publishing; the pre-conflict draft remains available as a separate new option.'};
			} else if (state.editing) {
				state.editing = null;
				state.draft = state.retainedDrafts.length ? state.retainedDrafts.shift().draft : state.draft;
				state.authoringContext = caseAuthoringContext(state.item);
				state.evidenceAttestation = null;
				state.dirty = true;
				state.notice = {type: 'info', message: 'The prior proposal is no longer revision-eligible. Its retained fields are now a new option draft and must be reviewed against the refreshed case.'};
			} else {
				state.notice = {type: 'info', message: 'The latest quote case and proposal history are loaded. Review the retained draft before publishing with a new protected request key.'};
			}
			state.pending = null;
			state.conflict = false;
			state.submitting = false;
			render(state);
		} catch (error) {
			state.submitting = false;
			state.notice = {type: 'error', message: error.message, code: error.code || ''};
			state.noticeFocus = true;
			render(state);
		}
	}

	async function withdrawProposal(state, proposal) {
		if (state.submitting || state.uncertain || !canCompose(state)) {
			return;
		}
		state.submitting = true;
		state.notice = null;
		var serialized = JSON.stringify({proposal_id: proposal.proposal_id, expected_version: Number(proposal.version)});
		if (!state.withdrawPending[proposal.proposal_id] || state.withdrawPending[proposal.proposal_id].serialized !== serialized) {
			state.withdrawPending[proposal.proposal_id] = {serialized: serialized, key: idempotencyKey('proposal-withdraw')};
		}
		render(state);
		try {
			var url = withdrawalUrl(state.config, state.item, proposal.proposal_id);
			var withdrawalBody = {expected_version: Number(proposal.version), idempotency_key: state.withdrawPending[proposal.proposal_id].key};
			var payload = await request(state.config, url, {method: 'POST', body: withdrawalBody});
			var withdrawalValidationContext = {
				case_id: caseId(state.item),
				proposal_id: proposal.proposal_id,
				expected_version: Number(proposal.version),
				expected_revision: Number(proposal.revision),
				expected_status: 'withdrawn',
				expected_case_revision: Number(proposal.addresses && proposal.addresses.case_revision),
				expected_request_digest: String(proposal.addresses && proposal.addresses.request_digest || ''),
			};
			var withdrawnProposal = mutationProposal(payload, withdrawalValidationContext);
			if (payload.replayed === true) {
				var withdrawalReconciliation = await reconcileProposalState(state, withdrawnProposal.proposal_id);
				if (withdrawalReconciliation.accessMoved) {
					completeAccessMovedReplay(state, 'withdraw', withdrawnProposal.proposal_id);
					render(state);
					return;
				}
				withdrawnProposal = withdrawalReconciliation.proposal;
			} else {
				state.proposals = mergeProposalMonotonic(state.proposals, withdrawnProposal);
				withdrawnProposal = state.proposals.find(function (candidate) { return candidate.proposal_id === withdrawnProposal.proposal_id; });
			}
			state.submitting = false;
			delete state.withdrawPending[proposal.proposal_id];
			state.uncertain = null;
			state.confirmWithdraw = '';
			state.notice = {type: 'success', message: payload.replayed === true
				? 'The protected withdrawal request was already handled. The latest authoritative proposal state is shown.'
				: 'The proposal was withdrawn without changing its immutable commercial revision.'};
			render(state);
		} catch (error) {
			state.submitting = false;
			if (error.outcomeUncertain) {
				state.uncertain = {
					kind: 'withdraw',
					url: url,
					body: cloneDraft(withdrawalBody),
					proposal_id: proposal.proposal_id,
					validation_context: {
						expected_status: 'withdrawn',
						expected_revision: Number(proposal.revision),
						expected_case_revision: Number(proposal.addresses && proposal.addresses.case_revision),
						expected_request_digest: String(proposal.addresses && proposal.addresses.request_digest || ''),
					},
				};
			}
			state.confirmWithdraw = '';
			state.notice = {type: 'error', message: error.message, code: error.code || ''};
			state.noticeFocus = true;
			render(state);
		}
	}

	function mount(parent, item, config, mountOptions) {
		var root = element('div', 'tra-vel-proposal-mount');
		parent.appendChild(root);
		var restored = mountOptions && mountOptions.restoreState;
		var options = {};
		Object.keys(mountOptions || {}).forEach(function (key) {
			if (key !== 'restoreState') {
				options[key] = mountOptions[key];
			}
		});
		if (restored && caseId(restored.item) === caseId(item)) {
			restored.root = root;
			if (!restored.dirty && !restored.editing && !restored.pending && !restored.uncertain) {
				restored.authoringContext = caseAuthoringContext(item);
			}
			restored.item = item;
			restored.config = config || {};
			restored.options = options;
			restored.focusHeading = false;
			render(restored);
			return restored;
		}
		var state = {
			root: root,
			item: item,
			config: config || {},
			options: options,
			instance: String(caseId(item) || Date.now()).replace(/[^a-z0-9]/gi, '').slice(0, 16),
			draft: defaultDraft(item),
			authoringContext: caseAuthoringContext(item),
			evidenceAttestation: null,
			proposals: [],
			loading: true,
			submitting: false,
			confirmWithdraw: '',
			pending: null,
			uncertain: null,
			withdrawPending: {},
			conflict: false,
			accessMoved: false,
			retainedDrafts: [],
			retentionCapacityReached: false,
			dirty: false,
			notice: null,
			noticeFocus: false,
			focusHeading: true,
		};
		render(state);
		loadProposals(state);
		return state;
	}

	window.TraVelAssistedProposalComposer = {
		mount: mount,
		majorToMinor: majorToMinor,
		buildComposition: buildComposition,
		request: request,
		mutationProposal: mutationProposal,
		proposalShapeValid: proposalShapeValid,
		proposalKeys: proposalKeys,
		exactKeys: exactKeys,
		isDateTime: isDateTime,
		uniqueStrings: uniqueStrings,
		nextActionsValid: nextActionsValid,
		sourceShapeValid: sourceShapeValid,
		priceShapeValid: priceShapeValid,
		ledgerMatchesComponents: ledgerMatchesComponents,
		compositionUrl: compositionUrl,
		evidenceAttestationUrl: evidenceAttestationUrl,
		withdrawalUrl: withdrawalUrl,
		draftFromProposal: draftFromProposal,
		proposalList: proposalList,
		mergeProposalMonotonic: mergeProposalMonotonic,
		reconcileProposalState: reconcileProposalState,
		completeAccessMovedReplay: completeAccessMovedReplay,
		retainDraft: retainDraft,
		invalidateComponentSources: invalidateComponentSources,
		removeSourceAt: removeSourceAt,
		expectedVersion: proposalExpectedVersion,
		caseAuthoringContext: caseAuthoringContext,
		validAuthoringContext: validAuthoringContext,
		publicSourceProviders: publicSourceProviders,
		publicSourceRelationships: publicSourceRelationships,
		reconcileSourceProviderPolicy: reconcileSourceProviderPolicy,
		evidenceAttestationCurrent: evidenceAttestationCurrent,
		attestationResponse: attestationResponse,
	};
}());
