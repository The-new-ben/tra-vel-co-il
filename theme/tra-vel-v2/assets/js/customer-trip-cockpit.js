(() => {
  'use strict';

  const runtime = {controller: null, lastView: null, loading: false, closing: false, suspended: false, expiryTimer: 0, everVisible: false};

  function exactObject(value, keys) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
    const actual = Object.keys(value);
    return actual.length === keys.length && keys.every(key => actual.includes(key));
  }

  function isoDate(value) {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value) && !Number.isNaN(Date.parse(value));
  }

  function viewValid(view, expectedMode) {
    const rootKeys = ['contract_version','environment','audience','trip_headline','current','next_safe_action','protections','changes','attention_items','service_timeline','customer_money','case_progress_disclosure','trip_care_cases','trip_care_receipts','traveler_readiness_disclosure','traveler_readiness','loyalty','offline_pack','freshness','authority','data_boundary'];
    if (!exactObject(view, rootKeys) || view.contract_version !== '1.0.0' || view.environment !== 'sandbox') return false;
    const audienceKeys = ['mode','view_allowed','report_issue_allowed','follow_up_allowed','high_impact_step_up_required','mutation_authorized'];
    if (!exactObject(view.audience, audienceKeys) || view.audience.mode !== expectedMode || view.audience.view_allowed !== true || view.audience.mutation_authorized !== false || view.audience.high_impact_step_up_required !== true) return false;
    const currentKeys = ['phase','health','registration_readiness','affected_service_count','unaffected_service_count','declared_affected_service_keys','partition_detail','action_required','verified_at'];
    if (!exactObject(view.current, currentKeys) || !isoDate(view.current.verified_at) || !Number.isInteger(view.current.affected_service_count) || !Number.isInteger(view.current.unaffected_service_count)) return false;
    if (!Array.isArray(view.service_timeline) || view.service_timeline.length < 1 || view.service_timeline.length > 64 || !Array.isArray(view.trip_care_cases) || !Array.isArray(view.trip_care_receipts)) return false;
    const authority = view.authority;
    if (!authority || authority.authorization_effect !== 'none' || authority.view_projection_only !== true || ['change_started','cancellation_started','payment_started','refund_started','supplier_action_started','processor_action_started','resolution_inferred'].some(key => authority[key] !== false)) return false;
    const boundary = view.data_boundary;
    if (!boundary || boundary.customer_serialization_allowed !== true || boundary.validated_private_read_model_only !== true || ['owner_scope_exposed','internal_refs_exposed','raw_identity_data_exposed','raw_payment_data_exposed','raw_medical_data_exposed','raw_provider_data_exposed','bearer_secret_exposed','internal_operator_routing_exposed','settlement_data_exposed','commission_data_exposed'].some(key => boundary[key] !== false)) return false;
    if (!view.freshness || !isoDate(view.freshness.source_verified_at) || !isoDate(view.freshness.projected_at)) return false;
    if (expectedMode === 'scoped_session' && (view.customer_money?.disclosure !== 'withheld_scoped_session' || view.traveler_readiness_disclosure !== 'withheld_scoped_session' || view.loyalty?.disclosure !== 'withheld_scoped_session')) return false;
    return view.service_timeline.every(service => service && typeof service === 'object' && Number.isInteger(service.sequence) && service.sequence > 0 && typeof service.vertical === 'string' && typeof service.phase === 'string' && typeof service.health === 'string' && isoDate(service.verified_at));
  }

  function label(group, value) {
    const labels = {
      headline: {'Trip plan':'תוכנית נסיעה','Upcoming trip':'נסיעה קרובה','Outbound journey':'הדרך ליעד','Trip in progress':'הנסיעה בעיצומה','Return journey':'הדרך חזרה','Completed trip':'נסיעה שהסתיימה'},
      phase: {planning:'תכנון',pre_trip:'לפני היציאה',outbound:'בדרך ליעד',in_trip:'במהלך הנסיעה',return_trip:'בדרך חזרה',post_trip:'אחרי הנסיעה'},
      health: {on_track:'הכול במסלול',watching:'עוקבים עבורכם',action_required:'נדרשת פעולה',disrupted:'חל שינוי',recovery_in_progress:'הטיפול מתקדם',uncertain:'ממתינים לאימות',completed_with_issue:'הסתיים עם נושא בטיפול'},
      freshness: {verified_current:'נבדק ועדכני',observed_unverified:'התקבל עדכון, ממתינים לאימות',stale:'נדרש רענון',uncertain:'המידע בבדיקה',conflict:'בודקים מידע סותר'},
      vertical: {flight:'טיסה',accommodation:'לינה',package:'חבילה',transfer:'העברה',activity:'פעילות',dining:'מסעדה',insurance:'ביטוח',connectivity:'תקשורת',equipment:'ציוד'},
      servicePhase: {planned:'בתכנון',held:'נשמר זמנית',confirmed:'מאושר',travel_ready:'מוכן לנסיעה',in_progress:'מתבצע עכשיו',completed:'הושלם',cancelled:'בוטל',recovery:'בטיפול'},
      action: {'view.trip_status':'בדקו את מצב הנסיעה','report.issue':'דווחו על בעיה','case.follow_up':'המשיכו את הטיפול בפנייה','question.follow_up':'השלימו פרט קצר','provide.trip.update':'עדכנו פרט בנסיעה','provide.trip.details':'השלימו את פרטי הנסיעה','change.flight':'בדקו שינוי בטיסה','approve.flight.change':'בדקו ואשרו את חלופת הטיסה','confirm.preference':'אשרו את ההעדפה','view.offline_itinerary':'פתחו את המסלול לשימוש ללא רשת','refresh.offline_pack':'רעננו את חבילת הנסיעה'},
      caseState: {case_received:'הפנייה התקבלה',immediate_safety_help:'סיוע מיידי',action_required:'ממתינים לפעולה שלכם',recovery_underway:'הטיפול מתקדם',attention_needed:'נדרש פרט נוסף',recovered:'הנושא טופל',resolved_with_loss:'הטיפול הסתיים עם יתרה לבירור',received:'העדכון התקבל',checking:'בודקים ומעדכנים',need_information:'חסר פרט קצר',human_review:'נציג מטפל בפנייה'}
    };
    return labels[group]?.[value] || 'בבדיקה';
  }

  function displayDate(value) {
    if (!isoDate(value)) return 'בבדיקה';
    try {
      return new Intl.DateTimeFormat('he-IL', {dateStyle:'medium',timeStyle:'short'}).format(new Date(value));
    } catch (_) {
      return new Date(value).toLocaleString('he-IL');
    }
  }

  function refreshIcons() {
    window.lucide?.createIcons?.({attrs: {'aria-hidden':'true'}});
  }

  function announce(root, message) {
    const node = root.querySelector('[data-customer-trip-cockpit-announcer]');
    if (node) node.textContent = message;
  }

  function setState(root, state, message = '') {
    root.dataset.state = state;
    root.setAttribute('aria-busy', String(state === 'loading'));
    const loading = root.querySelector('[data-customer-trip-cockpit-loading]');
    const view = root.querySelector('[data-customer-trip-cockpit-view]');
    const empty = root.querySelector('[data-customer-trip-cockpit-empty]');
    const error = root.querySelector('[data-customer-trip-cockpit-error]');
    if (loading) loading.hidden = state !== 'loading';
    if (view) view.hidden = !['ready','stale','stale_error'].includes(state);
    if (empty) empty.hidden = !['empty','expired'].includes(state);
    if (error) error.hidden = !['error','stale_error','reauthenticate','rate_limited'].includes(state);
    if (message) announce(root, message);
  }

  function clearPrivateView(root) {
    runtime.lastView = null;
    if (runtime.expiryTimer) window.clearTimeout(runtime.expiryTimer);
    runtime.expiryTimer = 0;
    [
      '[data-customer-trip-headline]',
      '[data-customer-trip-phase]',
      '[data-customer-trip-health]',
      '[data-customer-trip-affected]',
      '[data-customer-trip-verified]',
      '[data-customer-trip-freshness]',
      '[data-customer-trip-action-label]',
      '[data-customer-trip-action-detail]'
    ].forEach(selector => {
      const node = root.querySelector(selector);
      if (node) {
        node.textContent = '';
        node.removeAttribute('data-state');
        node.removeAttribute('data-freshness');
      }
    });
    root.querySelector('[data-customer-trip-services]')?.replaceChildren();
    root.querySelector('[data-customer-trip-case-list]')?.replaceChildren();
    const action = root.querySelector('[data-customer-trip-next-action]');
    const actionButton = root.querySelector('[data-customer-trip-action]');
    const cases = root.querySelector('[data-customer-trip-cases]');
    const view = root.querySelector('[data-customer-trip-cockpit-view]');
    if (action) action.hidden = true;
    if (actionButton) {
      actionButton.hidden = true;
      actionButton.dataset.actionCode = '';
    }
    if (cases) cases.hidden = true;
    if (view) view.hidden = true;
  }

  function scheduleAccessRefresh(root, accessExpiresAt) {
    if (runtime.expiryTimer) window.clearTimeout(runtime.expiryTimer);
    const parsedExpiry = isoDate(accessExpiresAt) ? Date.parse(accessExpiresAt) : Date.now() + 300000;
    const delay = Math.max(0, Math.min(300000, parsedExpiry - Date.now()));
    runtime.expiryTimer = window.setTimeout(() => {
      clearPrivateView(root);
      load();
    }, delay);
  }

  function renderAction(root, action, audience) {
    const card = root.querySelector('[data-customer-trip-next-action]');
    const button = root.querySelector('[data-customer-trip-action]');
    if (!card) return;
    if (!action || typeof action !== 'object') {
      card.hidden = true;
      if (button) button.hidden = true;
      return;
    }
    const title = root.querySelector('[data-customer-trip-action-label]');
    const detail = root.querySelector('[data-customer-trip-action-detail]');
    if (title) title.textContent = label('action', action.code);
    if (detail) {
      const deadline = isoDate(action.deadline) ? ` עד ${displayDate(action.deadline)}.` : '';
      detail.textContent = action.interaction_mode === 'step_up_required'
        ? `נבקש אימות ואישור לפני כל פעולה.${deadline}`
        : `אפשר להמשיך מכאן בלי לחפש את פרטי ההזמנה.${deadline}`;
    }
    if (button) {
      const mayContinue = audience?.report_issue_allowed === true || audience?.follow_up_allowed === true;
      button.hidden = !mayContinue;
      button.dataset.actionCode = mayContinue && typeof action.code === 'string' ? action.code : '';
    }
    card.hidden = false;
  }

  function renderServices(root, services) {
    const list = root.querySelector('[data-customer-trip-services]');
    if (!list) return;
    list.replaceChildren();
    [...services].sort((a,b) => a.sequence - b.sequence).forEach((service,index) => {
      const item = document.createElement('li');
      item.className = 'customer-trip-service';
      item.dataset.health = service.health;
      item.style.setProperty('--service-index', String(index));
      const copy = document.createElement('div');
      const title = document.createElement('strong');
      title.textContent = `${label('vertical', service.vertical)} ${label('servicePhase', service.phase)}`;
      const meta = document.createElement('small');
      const impact = service.impact_state === 'affected' ? 'השירות מושפע משינוי' : service.impact_state === 'unaffected' ? 'השירות לא הושפע' : 'ההשפעה עדיין בבדיקה';
      meta.textContent = `${impact}. עודכן ${displayDate(service.verified_at)}`;
      copy.append(title, meta);
      const state = document.createElement('span');
      state.dataset.state = service.health;
      state.textContent = label('health', service.health);
      item.append(copy, state);
      list.append(item);
    });
  }

  function renderCases(root, view) {
    const section = root.querySelector('[data-customer-trip-cases]');
    const list = root.querySelector('[data-customer-trip-case-list]');
    if (!section || !list) return;
    list.replaceChildren();
    const cases = view.audience.follow_up_allowed ? [
      ...view.trip_care_cases.map(item => ({...item,kind:'case'})),
      ...view.trip_care_receipts.map(item => ({...item,kind:'receipt'}))
    ] : [];
    cases.forEach(item => {
      const card = document.createElement('article');
      card.className = 'customer-trip-case';
      const eyebrow = document.createElement('small');
      eyebrow.textContent = item.kind === 'case' ? 'פנייה פעילה' : 'עדכון שנשלח';
      const title = document.createElement('strong');
      title.textContent = label('caseState', item.customer_state);
      const verified = document.createElement('span');
      verified.textContent = `עודכן ${displayDate(item.verified_at)}`;
      card.append(eyebrow, title, verified);
      list.append(card);
    });
    section.hidden = cases.length === 0;
  }

  function syncModeControls(root) {
    const signedIn = root.dataset.mode === 'signed-in';
    const mode = root.querySelector('.customer-trip-cockpit-mode');
    const labelNode = root.querySelector('[data-customer-trip-mode-label]');
    const icon = mode?.querySelector('[data-lucide]');
    if (labelNode && mode) labelNode.textContent = signedIn ? mode.dataset.signedLabel : mode.dataset.scopedLabel;
    if (icon) icon.setAttribute('data-lucide', signedIn ? 'user-round-check' : 'link-2');
    const close = root.querySelector('[data-customer-trip-cockpit-close]');
    if (close) close.hidden = signedIn;
    root.querySelectorAll('[data-customer-trip-mode-select]').forEach(button => {
      button.setAttribute('aria-pressed', String(button.dataset.customerTripModeSelect === root.dataset.mode));
    });
  }

  function renderView(root, view) {
    const mode = root.dataset.mode === 'signed-in' ? 'signed_in' : 'scoped_session';
    if (!viewValid(view, mode)) throw new Error('customer_trip_contract_invalid');
    root.hidden = false;
    runtime.everVisible = true;
    syncModeControls(root);
    const setText = (selector,value) => { const target = root.querySelector(selector); if (target) target.textContent = value; };
    setText('[data-customer-trip-headline]', label('headline', view.trip_headline));
    setText('[data-customer-trip-phase]', label('phase', view.current.phase));
    setText('[data-customer-trip-health]', label('health', view.current.health));
    setText('[data-customer-trip-affected]', `${view.current.affected_service_count} מתוך ${view.current.affected_service_count + view.current.unaffected_service_count}`);
    setText('[data-customer-trip-verified]', displayDate(view.freshness.source_verified_at));
    const health = root.querySelector('[data-customer-trip-health]');
    if (health) health.dataset.state = view.current.health;
    const freshness = root.querySelector('[data-customer-trip-freshness]');
    if (freshness) {
      freshness.dataset.freshness = view.freshness.status;
      freshness.textContent = label('freshness', view.freshness.status);
    }
    renderAction(root, view.next_safe_action, view.audience);
    renderServices(root, view.service_timeline);
    renderCases(root, view);
    runtime.lastView = view;
    const state = view.freshness.status === 'verified_current' ? 'ready' : 'stale';
    setState(root, state, state === 'ready' ? 'מצב הנסיעה עודכן.' : 'מוצג העדכון האחרון שאומת; מידע נוסף נמצא בבדיקה.');
    refreshIcons();
  }

  function setErrorContent(root, title, copy, action = 'retry') {
    const titleNode = root.querySelector('[data-customer-trip-error-title]');
    const copyNode = root.querySelector('[data-customer-trip-error-copy]');
    const button = root.querySelector('[data-customer-trip-cockpit-retry]');
    if (titleNode) titleNode.textContent = title;
    if (copyNode) copyNode.textContent = copy;
    if (button) {
      button.hidden = action === 'none';
      button.dataset.action = action;
      button.textContent = action === 'login' ? 'התחברו מחדש' : 'נסו שוב';
    }
  }

  async function load({manual = false} = {}) {
    const root = document.querySelector('[data-customer-trip-cockpit]');
    const endpoint = window.traVelV2?.customerTripCockpitUrl;
    if (!root || !endpoint || runtime.loading || runtime.closing || runtime.suspended) return;
    runtime.loading = true;
    root.setAttribute('aria-busy', 'true');
    const refreshButton = root.querySelector('[data-customer-trip-cockpit-refresh]');
    if (refreshButton) refreshButton.disabled = true;
    runtime.controller?.abort();
    const controller = typeof AbortController === 'function' ? new AbortController() : null;
    runtime.controller = controller;
    if (!runtime.lastView) setState(root, 'loading', manual ? 'מרעננים את מצב הנסיעה.' : 'בודקים את מצב הנסיעה.');
    const signedIn = root.dataset.mode === 'signed-in';
    const headers = {'Accept':'application/json','X-Tra-Vel-Cockpit-Read':'1','X-Tra-Vel-Cockpit-Mode':signedIn ? 'signed-in' : 'scoped-session'};
    if (signedIn) headers['X-WP-Nonce'] = window.traVelV2?.nonce || '';
    let timeout = 0;
    try {
      if (controller) timeout = window.setTimeout(() => controller.abort(), 12000);
      const response = await fetch(endpoint, {method:'GET',credentials:'same-origin',cache:'no-store',referrerPolicy:'no-referrer',headers,signal:controller?.signal});
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const error = new Error(typeof payload?.message === 'string' ? payload.message : `customer_trip_${response.status}`);
        error.status = response.status;
        error.code = typeof payload?.code === 'string' ? payload.code : '';
        throw error;
      }
      renderView(root, payload);
      scheduleAccessRefresh(root, response.headers.get('X-Tra-Vel-Cockpit-View-Expires'));
    } catch (error) {
      if (error?.name === 'AbortError' && runtime.controller !== controller) return;
      const status = Number(error?.status) || 0;
      if ([401,403,404].includes(status)) clearPrivateView(root);
      if (runtime.lastView && (status === 0 || status >= 500)) {
        setErrorContent(root, 'לא התקבל עדכון חדש', 'העדכון האחרון שאומת נשאר מוצג. אפשר לנסות לרענן שוב.');
        setState(root, 'stale_error', 'לא התקבל עדכון חדש. העדכון האחרון שאומת נשאר מוצג.');
      } else if (status === 404 && signedIn) {
        root.hidden = false;
        runtime.everVisible = true;
        const title = root.querySelector('[data-customer-trip-empty-title]');
        const copy = root.querySelector('[data-customer-trip-empty-copy]');
        if (title) title.textContent = 'אין כרגע נסיעה פעילה בחשבון';
        if (copy) copy.textContent = 'כאשר נסיעה תועבר לטיפול, המצב המלא שלה יופיע כאן.';
        setState(root, 'empty', 'אין כרגע נסיעה פעילה בחשבון.');
      } else if ([401,403,404].includes(status) && !signedIn) {
        const title = root.querySelector('[data-customer-trip-empty-title]');
        const copy = root.querySelector('[data-customer-trip-empty-copy]');
        if (title) title.textContent = 'קישור הצפייה אינו פעיל';
        if (copy) copy.textContent = 'בקשו קישור מאובטח חדש כדי לחזור למצב הנסיעה.';
        if (runtime.everVisible || window.traVelV2?.isLoggedIn) {
          root.hidden = false;
          runtime.everVisible = true;
          setState(root, 'expired', 'קישור הצפייה אינו פעיל.');
        } else {
          root.hidden = true;
        }
      } else if ([401,403].includes(status) && signedIn) {
        root.hidden = false;
        runtime.everVisible = true;
        setErrorContent(root, 'החיבור לחשבון הסתיים', 'התחברו מחדש כדי לראות את מצב הנסיעה.', 'login');
        setState(root, 'reauthenticate', 'החיבור לחשבון הסתיים.');
      } else if (status === 429) {
        root.hidden = false;
        runtime.everVisible = true;
        setErrorContent(root, 'בוצעו יותר מדי רענונים', 'המתינו מעט ונסו שוב.');
        setState(root, 'rate_limited', 'בוצעו יותר מדי רענונים.');
      } else {
        root.hidden = false;
        runtime.everVisible = true;
        if (status >= 400 && status < 500) clearPrivateView(root);
        setErrorContent(root, 'מצב הנסיעה אינו זמין כרגע', 'לא הוצג מידע לא מאומת. אפשר לנסות שוב בעוד רגע.');
        setState(root, 'error', 'מצב הנסיעה אינו זמין כרגע.');
      }
      refreshIcons();
    } finally {
      if (timeout) window.clearTimeout(timeout);
      if (runtime.controller === controller) {
        runtime.controller = null;
        runtime.loading = false;
        if (refreshButton) refreshButton.disabled = false;
        if (root.dataset.state !== 'loading') root.setAttribute('aria-busy', 'false');
      }
    }
  }

  async function closeScopedSession(root, button) {
    const endpoint = window.traVelV2?.capabilitySessionLogoutUrl;
    if (!endpoint || root.dataset.mode !== 'scoped-session' || button.disabled || runtime.closing) return;
    runtime.closing = true;
    runtime.controller?.abort();
    runtime.controller = null;
    runtime.loading = false;
    button.disabled = true;
    root.setAttribute('aria-busy', 'true');
    const headers = {'Accept':'application/json'};
    if (window.traVelV2?.isLoggedIn) headers['X-WP-Nonce'] = window.traVelV2?.nonce || '';
    try {
      const response = await fetch(endpoint, {method:'POST',credentials:'same-origin',cache:'no-store',referrerPolicy:'no-referrer',headers});
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || payload?.closed !== true) throw new Error('customer_trip_logout_failed');
      clearPrivateView(root);
      runtime.everVisible = false;
      root.hidden = true;
      window.location.assign(window.traVelV2?.homeUrl || '/');
    } catch (_) {
      root.hidden = false;
      if (runtime.lastView) {
        setErrorContent(root, 'לא הצלחנו לסיים את הצפייה', 'הנסיעה עדיין מוצגת במכשיר הזה. נסו שוב כדי לסגור את הקישור המאובטח.');
        setState(root, 'stale_error', 'לא הצלחנו לסיים את הצפייה המאובטחת.');
      } else {
        setErrorContent(root, 'לא הצלחנו לסיים את הצפייה', 'נסו שוב בעוד רגע.');
        setState(root, 'error', 'לא הצלחנו לסיים את הצפייה המאובטחת.');
      }
    } finally {
      runtime.closing = false;
      button.disabled = false;
      root.setAttribute('aria-busy', 'false');
      refreshIcons();
    }
  }

  function init() {
    const root = document.querySelector('[data-customer-trip-cockpit]');
    if (!root) return;
    root.querySelector('[data-customer-trip-cockpit-retry]')?.addEventListener('click', event => {
      if (event.currentTarget.dataset.action === 'login') {
        window.location.assign(window.traVelV2?.loginUrl || '/wp-login.php');
        return;
      }
      load({manual:true});
    });
    root.querySelector('[data-customer-trip-cockpit-refresh]')?.addEventListener('click', () => load({manual:true}));
    root.querySelector('[data-customer-trip-cockpit-close]')?.addEventListener('click', event => closeScopedSession(root, event.currentTarget));
    root.querySelector('[data-customer-trip-action]')?.addEventListener('click', () => {
      window.location.assign(window.traVelV2?.tripCareUrl || '/ai-planner/');
    });
    root.querySelectorAll('[data-customer-trip-mode-select]').forEach(button => {
      button.addEventListener('click', () => {
        const nextMode = button.dataset.customerTripModeSelect;
        if (runtime.closing || !['signed-in','scoped-session'].includes(nextMode) || nextMode === root.dataset.mode) return;
        runtime.controller?.abort();
        runtime.controller = null;
        runtime.loading = false;
        clearPrivateView(root);
        root.dataset.mode = nextMode;
        root.hidden = false;
        syncModeControls(root);
        setState(root, 'loading', 'בודקים את מצב הנסיעה שבחרתם.');
        refreshIcons();
        load({manual:true});
      });
    });
    window.addEventListener('pagehide', () => {
      runtime.suspended = true;
      runtime.controller?.abort();
      runtime.controller = null;
      runtime.loading = false;
      clearPrivateView(root);
      root.hidden = true;
    });
    window.addEventListener('pageshow', event => {
      if (!runtime.suspended && !event.persisted) return;
      runtime.suspended = false;
      runtime.closing = false;
      root.hidden = true;
      clearPrivateView(root);
      load();
    });
    syncModeControls(root);
    load();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
  else init();
})();
