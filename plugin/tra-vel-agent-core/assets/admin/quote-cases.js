(function () {
	'use strict';

	var root = document.getElementById('tra-vel-quote-cases-app');
	var config = window.TraVelQuoteCasesAdmin || {};

	if (!root) {
		return;
	}

	var state = {
		cases: [],
		filter: 'all',
		loading: true,
		transitioning: {},
		animateRows: false,
		updatedCase: '',
		notice: null,
		page: 1,
		meta: { count: 0, total: 0, page: 1, per_page: 50 },
		knownTotals: {},
	};

	var fallbackStatuses = {
		queued: { label: 'Queued', next: ['in_review', 'needs_information', 'closed_no_quote'] },
		in_review: { label: 'In review', next: ['needs_information', 'ready_for_assistance', 'closed_no_quote'] },
		needs_information: { label: 'Needs information', next: ['queued', 'in_review', 'closed_no_quote'] },
		ready_for_assistance: { label: 'Ready for assistance', next: ['in_review', 'needs_information', 'closed_no_quote'] },
		closed_no_quote: { label: 'Closed without assistance', next: [] },
		cancelled: { label: 'Cancelled', next: [] },
		expired: { label: 'Expired', next: [] },
	};

	var statuses = normalizeStatuses(config.statuses);
	var liveRegion;

	function normalizeStatuses(input) {
		var source = input && typeof input === 'object' && !Array.isArray(input) ? input : fallbackStatuses;
		var normalized = {};

		Object.keys(source).forEach(function (key) {
			var definition = source[key] || {};
			normalized[key] = {
				label: typeof definition.label === 'string' ? definition.label : humanize(key),
				next: Array.isArray(definition.next) ? definition.next.slice() : [],
			};
		});

		return Object.keys(normalized).length ? normalized : fallbackStatuses;
	}

	function humanize(value) {
		var text = String(value || 'unknown').replace(/[_-]+/g, ' ');
		return text.charAt(0).toUpperCase() + text.slice(1);
	}

	function element(tagName, className, text) {
		var item = document.createElement(tagName);
		if (className) {
			item.className = className;
		}
		if (typeof text === 'string') {
			item.textContent = text;
		}
		return item;
	}

	function statusDefinition(status) {
		return statuses[status] || { label: humanize(status), next: [] };
	}

	function caseId(item) {
		return String(item.case_uuid || item.case_id || item.id || '');
	}

	function caseReference(item) {
		return String(item.reference || item.reference_code || item.case_reference || item.public_reference || item.case_uuid || item.case_id || item.id || '-');
	}

	function caseVersion(item) {
		var version = Number(item.version || item.case_version || item.status_version || 1);
		return Number.isFinite(version) && version > 0 ? Math.floor(version) : 1;
	}

	function parseSnapshot(snapshot) {
		if (!snapshot) {
			return null;
		}
		if (typeof snapshot === 'object') {
			return snapshot;
		}
		if (typeof snapshot !== 'string') {
			return null;
		}
		try {
			return JSON.parse(snapshot);
		} catch (error) {
			return null;
		}
	}

	function describeCase(item) {
		var direct = [item.summary, item.request_summary, item.trip_summary].find(function (value) {
			return typeof value === 'string' && value.trim();
		});
		if (direct) {
			return direct.trim();
		}

		var snapshot = parseSnapshot(item.snapshot || item.request_summary || item.summary || item.request_snapshot || item.trip_request || item.request);
		if (!snapshot) {
			return 'Structured traveler request';
		}

		var request = snapshot.trip_request || snapshot;
		if (typeof request.title === 'string' && request.title.trim()) {
			return request.title.trim();
		}
		if (typeof request.summary === 'string' && request.summary.trim()) {
			return request.summary.trim();
		}
		var parts = [];
		var destinations = request.destinations || request.destination_intents || request.destination;
		if (typeof destinations === 'string' && destinations.trim()) {
			parts.push(destinations.trim());
		} else if (Array.isArray(destinations)) {
			var names = destinations.map(function (destination) {
				if (typeof destination === 'string') {
					return destination;
				}
				if (!destination || typeof destination !== 'object') {
					return '';
				}
				return destination.label || destination.name || destination.city || destination.country || '';
			}).filter(Boolean);
			if (names.length) {
				parts.push(names.slice(0, 3).join(', '));
			}
		}

		var travelers = request.travelers || request.party || request.traveler_count;
		if (typeof travelers === 'number' || typeof travelers === 'string') {
			parts.push(String(travelers) + ' travelers');
		} else if (travelers && typeof travelers === 'object') {
			var count = Number(travelers.total || travelers.count || travelers.adults || 0);
			if (count > 0) {
				parts.push(String(count) + ' travelers');
			}
		}

		return parts.length ? parts.join(' | ') : 'Structured traveler request';
	}

	function assignmentLabel(item) {
		var assignment = item.assignment || item.assigned_operator || item.assignee;
		if (typeof assignment === 'string' && assignment.trim()) {
			return assignment.trim();
		}
		if (assignment && typeof assignment === 'object') {
			return assignment.display_name || assignment.name || assignment.email || 'Assigned operator';
		}
		return item.assigned_to_display_name || item.operator_display_name || (item.assigned_to || item.operator_user_id || item.assigned_user_id ? 'Assigned operator' : 'Unassigned');
	}

	function formatDate(value) {
		if (!value) {
			return '-';
		}
		var candidate = new Date(String(value).replace(' ', 'T'));
		if (Number.isNaN(candidate.getTime())) {
			return String(value);
		}
		try {
			return new Intl.DateTimeFormat(document.documentElement.lang || 'en', {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(candidate);
		} catch (error) {
			return candidate.toLocaleString();
		}
	}

	function idempotencyKey() {
		var suffix = window.crypto && typeof window.crypto.randomUUID === 'function'
			? window.crypto.randomUUID()
			: Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
		return 'operator-transition-' + suffix;
	}

	async function request(url, options) {
		var requestOptions = options || {};
		var headers = {
			Accept: 'application/json',
			'X-WP-Nonce': String(config.nonce || ''),
		};

		if (requestOptions.body) {
			headers['Content-Type'] = 'application/json';
		}

		var response = await window.fetch(url, {
			method: requestOptions.method || 'GET',
			headers: headers,
			credentials: 'same-origin',
			body: requestOptions.body ? JSON.stringify(requestOptions.body) : undefined,
		});
		var payload = null;
		try {
			payload = await response.json();
		} catch (error) {
			payload = null;
		}

		if (!response.ok) {
			var message = payload && payload.message ? payload.message : 'The operator queue could not complete this request.';
			var apiError = new Error(message);
			apiError.status = response.status;
			throw apiError;
		}

		return payload || {};
	}

	function announce(message) {
		if (liveRegion) {
			liveRegion.textContent = '';
			window.requestAnimationFrame(function () {
				liveRegion.textContent = message;
			});
		}
	}

	function renderLoading() {
		root.replaceChildren();
		var loading = element('div', 'tra-vel-quote-cases-loading');
		loading.setAttribute('role', 'status');
		loading.setAttribute('aria-label', 'Loading quote cases');

		var toolbar = element('div', 'tra-vel-quote-cases-loading__toolbar tra-vel-skeleton');
		loading.appendChild(toolbar);

		for (var index = 0; index < 4; index += 1) {
			var row = element('div', 'tra-vel-quote-cases-loading__row');
			for (var cell = 0; cell < 5; cell += 1) {
				row.appendChild(element('span', 'tra-vel-skeleton'));
			}
			loading.appendChild(row);
		}

		root.appendChild(loading);
	}

	function countFor(status) {
		if (Object.prototype.hasOwnProperty.call(state.knownTotals, status)) {
			return state.knownTotals[status];
		}
		return null;
	}

	function renderFilters(parent) {
		var filters = element('div', 'tra-vel-quote-cases-filters');
		filters.setAttribute('role', 'group');
		filters.setAttribute('aria-label', 'Filter quote cases by status');

		var filterKeys = ['all'].concat(Object.keys(statuses));
		filterKeys.forEach(function (key) {
			var label = key === 'all' ? 'All' : statusDefinition(key).label;
			var knownCount = countFor(key);
			var button = element('button', 'tra-vel-quote-cases-filter', label + (knownCount === null ? '' : ' ' + knownCount));
			button.type = 'button';
			button.setAttribute('aria-pressed', state.filter === key ? 'true' : 'false');
			if (state.filter === key) {
				button.classList.add('is-active');
			}
			button.addEventListener('click', function () {
				if (state.filter === key) {
					return;
				}
				state.filter = key;
				state.page = 1;
				loadCases();
			});
			filters.appendChild(button);
		});

		parent.appendChild(filters);
	}

	function renderNotice(parent) {
		if (!state.notice || !state.notice.text) {
			return;
		}

		var type = state.notice.type === 'error' ? 'error' : (state.notice.type === 'success' ? 'success' : 'info');
		var notice = element('div', 'tra-vel-quote-cases-notice is-' + type + ' is-arriving', state.notice.text);
		notice.setAttribute('role', type === 'error' ? 'alert' : 'status');
		parent.appendChild(notice);
	}

	function addCell(row, label, child, className) {
		var cell = element('td', className || '');
		cell.dataset.label = label;
		if (typeof child === 'string') {
			cell.textContent = child;
		} else if (child) {
			cell.appendChild(child);
		}
		row.appendChild(cell);
	}

	function renderStatus(item) {
		var status = String(item.status || 'unknown');
		var badge = element('span', 'tra-vel-quote-case-status', statusDefinition(status).label);
		badge.dataset.status = status.replace(/[^a-z0-9_-]/gi, '');
		return badge;
	}

	function renderActions(item) {
		var container = element('div', 'tra-vel-quote-case-actions');
		var identifier = caseId(item);
		var transitioning = Boolean(state.transitioning[identifier]);
		var nextStatuses = Array.isArray(item.allowed_transitions)
			? item.allowed_transitions
			: statusDefinition(item.status).next;

		if (!config.canManage) {
			container.appendChild(element('span', 'tra-vel-quote-case-actions__complete', 'View only'));
			return container;
		}

		if (!nextStatuses.length) {
			container.appendChild(element('span', 'tra-vel-quote-case-actions__complete', 'No further operator action'));
			return container;
		}

		nextStatuses.forEach(function (nextStatus, index) {
			var label = statusDefinition(nextStatus).label;
			var button = element('button', 'button tra-vel-quote-case-action', transitioning ? 'Updating...' : label);
			button.type = 'button';
			button.disabled = transitioning || !identifier;
			button.classList.toggle('button-primary', index === 0);
			button.classList.toggle('is-running', transitioning);
			button.setAttribute('aria-label', 'Move ' + caseReference(item) + ' to ' + label);
			button.addEventListener('click', function () {
				transitionCase(item, nextStatus);
			});
			container.appendChild(button);
		});

		return container;
	}

	function visibleCases() {
		if (state.filter === 'all') {
			return state.cases;
		}
		return state.cases.filter(function (item) {
			return item.status === state.filter;
		});
	}

	function renderPagination(parent) {
		var perPage = Number(state.meta.per_page || 50);
		var total = Number(state.meta.total || 0);
		var currentPage = Number(state.meta.page || state.page || 1);
		var pages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));

		if (pages <= 1) {
			return;
		}

		var pagination = element('nav', 'tra-vel-quote-cases-pagination');
		pagination.setAttribute('aria-label', 'Quote case pages');
		var previous = element('button', 'button', 'Previous');
		previous.type = 'button';
		previous.disabled = currentPage <= 1;
		previous.addEventListener('click', function () {
			state.page = Math.max(1, currentPage - 1);
			loadCases();
		});

		var pageLabel = element('span', '', 'Page ' + currentPage + ' of ' + pages);

		var next = element('button', 'button', 'Next');
		next.type = 'button';
		next.disabled = currentPage >= pages;
		next.addEventListener('click', function () {
			state.page = Math.min(pages, currentPage + 1);
			loadCases();
		});

		pagination.appendChild(previous);
		pagination.appendChild(pageLabel);
		pagination.appendChild(next);
		parent.appendChild(pagination);
	}

	function renderTable(parent) {
		var items = visibleCases();
		if (!items.length) {
			var empty = element('div', 'tra-vel-quote-cases-empty');
			empty.appendChild(element('span', 'dashicons dashicons-yes-alt'));
			empty.appendChild(element('h2', '', state.cases.length ? 'No cases match this status' : 'The operator queue is clear'));
			empty.appendChild(element('p', '', state.cases.length ? 'Choose another status to continue reviewing cases.' : 'New assisted requests will appear here after a traveler submits one.'));
			parent.appendChild(empty);
			return;
		}

		var tableWrap = element('div', 'tra-vel-quote-cases-table-wrap');
		var table = element('table', 'widefat fixed striped tra-vel-quote-cases-table');
		var caption = element('caption', 'screen-reader-text', 'Assisted quote case operator queue');
		table.appendChild(caption);

		var header = document.createElement('thead');
		var headerRow = document.createElement('tr');
		['Reference', 'Traveler request', 'Status', 'Assignment', 'Updated', 'Next step'].forEach(function (label) {
			headerRow.appendChild(element('th', '', label));
		});
		header.appendChild(headerRow);
		table.appendChild(header);

		var body = document.createElement('tbody');
		items.forEach(function (item, index) {
			var row = element('tr', 'tra-vel-quote-case-row');
			if (state.animateRows) {
				row.classList.add('is-arriving');
			}
			if (state.updatedCase === caseId(item)) {
				row.classList.add('is-updated');
			}
			row.style.setProperty('--arrival-index', String(Math.min(index, 10)));
			row.dataset.caseId = caseId(item);

			var reference = element('strong', 'tra-vel-quote-case-reference', caseReference(item));
			addCell(row, 'Reference', reference, 'tra-vel-quote-case-cell--reference');
			addCell(row, 'Traveler request', describeCase(item), 'tra-vel-quote-case-cell--summary');
			addCell(row, 'Status', renderStatus(item), 'tra-vel-quote-case-cell--status');
			addCell(row, 'Assignment', assignmentLabel(item), 'tra-vel-quote-case-cell--assignment');
			addCell(row, 'Updated', formatDate(item.updated_at || item.modified_at), 'tra-vel-quote-case-cell--updated');
			addCell(row, 'Next step', renderActions(item), 'tra-vel-quote-case-cell--actions');
			body.appendChild(row);
		});
		table.appendChild(body);
		tableWrap.appendChild(table);
		parent.appendChild(tableWrap);
	}

	function render() {
		root.replaceChildren();

		var shell = element('section', 'tra-vel-quote-cases-shell');
		var overview = element('div', 'tra-vel-quote-cases-overview');
		var count = element('div', 'tra-vel-quote-cases-count');
		var total = Number(state.meta.total || 0);
		count.appendChild(element('strong', '', String(total)));
		count.appendChild(element('span', '', total === 1 ? ' case in this view' : ' cases in this view'));
		overview.appendChild(count);
		var refresh = element('button', 'button tra-vel-quote-cases-refresh', 'Refresh');
		refresh.type = 'button';
		refresh.addEventListener('click', loadCases);
		overview.appendChild(refresh);
		shell.appendChild(overview);

		renderNotice(shell);
		renderFilters(shell);
		renderTable(shell);
		renderPagination(shell);

		liveRegion = element('p', 'screen-reader-text');
		liveRegion.setAttribute('aria-live', 'polite');
		liveRegion.setAttribute('aria-atomic', 'true');
		shell.appendChild(liveRegion);
		root.appendChild(shell);
		state.animateRows = false;
		state.updatedCase = '';
	}

	function renderError(error) {
		root.replaceChildren();
		var notice = element('div', 'notice notice-error tra-vel-quote-cases-error');
		notice.setAttribute('role', 'alert');
		notice.appendChild(element('p', '', error && error.message ? error.message : 'The quote-case queue could not be loaded.'));
		var retry = element('button', 'button button-primary', 'Try again');
		retry.type = 'button';
		retry.addEventListener('click', loadCases);
		notice.appendChild(retry);
		root.appendChild(notice);
	}

	async function loadCases() {
		if (!config.restUrl || !config.nonce) {
			renderError(new Error('The operator queue is not configured.'));
			return;
		}

		state.loading = true;
		state.notice = null;
		renderLoading();
		try {
			var url = new URL(config.restUrl, window.location.origin);
			url.searchParams.set('page', String(state.page));
			url.searchParams.set('per_page', '50');
			if (state.filter !== 'all') {
				url.searchParams.set('status', state.filter);
			}
			var payload = await request(url.toString());
			var cases = Array.isArray(payload) ? payload : (payload.cases || payload.items || []);
			state.cases = Array.isArray(cases) ? cases : [];
			state.meta = payload && payload.meta ? payload.meta : {
				count: state.cases.length,
				total: state.cases.length,
				page: state.page,
				per_page: 50,
			};
			state.page = Number(state.meta.page || state.page || 1);
			state.knownTotals[state.filter] = Number(state.meta.total || state.cases.length);
			state.loading = false;
			state.animateRows = true;
			render();
			announce('Quote cases loaded.');
		} catch (error) {
			state.loading = false;
			renderError(error);
		}
	}

	async function transitionCase(item, nextStatus) {
		var identifier = caseId(item);
		if (!identifier || state.transitioning[identifier]) {
			return;
		}

		state.transitioning[identifier] = true;
		render();
		try {
			var url = String(config.restUrl).replace(/\/$/, '') + '/' + encodeURIComponent(identifier) + '/transitions';
			var payload = await request(url, {
				method: 'POST',
				body: {
					status: nextStatus,
					expected_version: caseVersion(item),
					idempotency_key: idempotencyKey(),
				},
			});
			var updated = payload.case || payload.item || payload.quote_case;
			if (updated) {
				state.cases = state.cases.map(function (candidate) {
					return caseId(candidate) === identifier ? updated : candidate;
				});
				if (state.filter !== 'all' && updated.status !== state.filter) {
					state.meta.total = Math.max(0, Number(state.meta.total || 0) - 1);
					state.meta.count = Math.max(0, Number(state.meta.count || state.cases.length) - 1);
					state.knownTotals[state.filter] = state.meta.total;
				}
			} else {
				await loadCases();
				return;
			}
			delete state.transitioning[identifier];
			state.updatedCase = identifier;
			state.notice = {
				type: 'success',
				text: caseReference(updated) + ' moved to ' + statusDefinition(updated.status).label + '.',
			};
			render();
			announce(state.notice.text);
		} catch (error) {
			delete state.transitioning[identifier];
			if (error.status === 409) {
				await loadCases();
				state.notice = { type: 'info', text: 'This case changed elsewhere. The queue has been refreshed.' };
				render();
				announce(state.notice.text);
				return;
			}
			state.notice = { type: 'error', text: error.message };
			render();
			announce(state.notice.text);
		}
	}

	loadCases();
}());
