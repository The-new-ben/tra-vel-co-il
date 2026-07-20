import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const themeRoot = join(repoRoot, 'theme', 'tra-vel-v2');
const experience = readFileSync(join(themeRoot, 'page-experience.php'), 'utf8');
const saved = readFileSync(join(themeRoot, 'page-saved.php'), 'utf8');
const app = readFileSync(join(themeRoot, 'assets/js/app.js'), 'utf8');
const css = readFileSync(join(themeRoot, 'assets/css/app.css'), 'utf8');
const failures = [];

const requireMarkers = (source, label, markers) => {
  for (const marker of markers) {
    if (!source.includes(marker)) failures.push(`${label} is missing ${marker}.`);
  }
};

requireMarkers(experience, 'AI planner quote-case panel', [
  'data-agent-quote-case',
  'data-quote-case-create-button',
  'data-quote-case-consent',
  'data-quote-case-reference',
  'data-quote-case-progress',
  'data-quote-case-next-action',
  'data-quote-case-events',
  'data-quote-case-handoff',
  'data-quote-case-cancel',
]);
if (experience.indexOf('data-agent-quote-case') < experience.indexOf('data-agent-supplier-state')) {
  failures.push('The quote-case panel must follow the supplier-state boundary in document flow.');
}

requireMarkers(saved, 'Traveler workspace quote-case section', [
  'data-workspace-quote-cases',
  'data-workspace-quote-status',
  'data-workspace-quote-grid',
  'data-workspace-quote-empty',
]);
if (!/data-workspace-quote-cases data-state="idle"[^>]+aria-busy="false"/.test(saved)) {
  failures.push('Workspace assistance must default to a calm non-busy state without JavaScript.');
}

requireMarkers(app, 'Quote-case browser client', [
  "agentRuntime.status === 'request_ready'",
  "agentApiRequest(`/runs/${encodeURIComponent(agentRuntime.runId)}/quote-cases`",
  'expected_request_id: agentRuntime.requestId',
  'expected_revision: agentRuntime.requestRevision',
  'consent: true',
  "consent_version: '2026-07-17'",
  'idempotency_key: button.dataset.idempotencyKey',
  "agentApiRequest('/quote-cases')",
  '/events?after=${agentRuntime.quoteCaseLastSequence}',
  "/handoffs`, {",
  "channel: 'whatsapp'",
  'expected_version: Number(caseData.version)',
  "/cancel`, {",
  "/claim`, {",
  "caseData?.ownership !== 'private_browser_owner'",
  'item?.source?.run_id === runId',
  'await loadQuoteCaseForRun(root)',
  'quoteCaseProgressState',
  'quoteCaseStepStateLabel',
  'quoteCaseCanResume',
  'isConfirmedQuoteCaseRecovery',
  'quoteCaseActiveStatuses',
  'quoteCaseTerminalStatuses',
  'const quoteCaseEventDomLimit = 100',
  'quoteCaseDiscardedSequence',
  'pollController: null',
  'pollGeneration: 0',
  "pollRunToken: ''",
  'quoteCasePollInFlight: false',
  'quoteCasePollController: null',
  'quoteCasePollGeneration: 0',
  "quoteCasePollCaseToken: ''",
  'function invalidateAgentPoll()',
  'function invalidateQuoteCasePoll()',
  'function isCurrentAgentPoll(runId, generation, controller)',
  'function isCurrentQuoteCasePoll(caseId, generation, controller)',
  'while (agentRuntime.quoteCaseEvents.length > quoteCaseEventDomLimit)',
  'log.firstElementChild?.remove()',
  '&limit=50',
  'events?.has_more === true',
  'if (added > 0 && !hasMore)',
  'const retryDelay = Math.min(120000',
  "[data-quote-case-error][data-source=\"poll\"]",
  "setQuoteCaseError(root, 'העדכון החי של הבדיקה האישית אינו זמין כרגע. המצב האחרון שאושר נשאר מוצג וננסה שוב אוטומטית.', 'poll')",
  'isConfirmedQuoteCaseForward(previousCase, caseData)',
  "nextCase.status === 'needs_information'",
  "progress.mode === 'terminal'",
  "progress.mode === 'blocked'",
  "actionStatus.dataset.state = payload?.replayed ? 'reused' : 'success'",
  "status.dataset.state = payload?.replayed ? 'reused' : 'success'",
  "url.hostname.toLowerCase() === 'api.whatsapp.com'",
]);
requireMarkers(app, 'Quote-case lead capture (theme 1.23.0)', [
  '...(acquisition ? {acquisition} : {})',
  'const acquisition = readAcquisition();',
  'function openQuoteCaseContactStep(root, view, button)',
  'function storeQuoteCaseLeadContact(contact)',
  'async function continueQuoteCaseWhatsappHandoff(root, existingPopup = null)',
  'continueQuoteCaseWhatsappHandoff(root, popup)',
  'quoteCaseContactSaved',
  'quoteCaseContactDeclined',
  'quoteCaseContactKey',
  "surface: 'quote_case_handoff'",
]);
if (!/if \(!agentRuntime\.quoteCaseContactSaved && !agentRuntime\.quoteCaseContactDeclined\) \{\s*openQuoteCaseContactStep\(root, view, button\);\s*return;\s*\}/.test(app)) {
  failures.push('The quote-case WhatsApp continuation must offer the inline contact step exactly once before opening the conversation.');
}
if (!/onSkip: async \(\) => \{\s*agentRuntime\.quoteCaseContactDeclined = true;[\s\S]{0,200}await continueQuoteCaseWhatsappHandoff\(root\);/.test(app)) {
  failures.push('Skipping the quote-case contact step must continue exactly like the pre-1.23.0 WhatsApp flow.');
}
if (/setInterval\s*\([^)]*(?:quote|case)/i.test(app)) {
  failures.push('Quote-case progress must not advance on a decorative interval.');
}
if (!/quoteCaseActiveStatuses\s*=\s*new Set\(\['queued', 'in_review', 'needs_information', 'ready_for_assistance'\]\)/.test(app)) {
  failures.push('The browser active-state set must match the server contract exactly.');
}
if (!/quoteCaseTerminalStatuses\s*=\s*new Set\(\['closed_no_quote', 'cancelled', 'expired'\]\)/.test(app)) {
  failures.push('The browser terminal-state set must match the server contract exactly.');
}
if (!/quoteCaseTerminalStatuses\.has\(status\)\) return \{current: 1, completed: 1, mode: 'terminal'\}/.test(app)
  || !/progress\.mode === 'terminal'[\s\S]*?index === progress\.current\) return 'terminal';[\s\S]*?return 'pending'/.test(app)) {
  failures.push('Terminal cases without event history may confirm only the initial structured request, never review or assistance.');
}
const workspaceCaseStart = app.indexOf('function renderWorkspaceQuoteCaseCard(');
const workspaceCaseEnd = app.indexOf('\nfunction renderWorkspaceQuoteCases(', workspaceCaseStart);
const workspaceCaseBody = workspaceCaseStart >= 0 && workspaceCaseEnd > workspaceCaseStart ? app.slice(workspaceCaseStart, workspaceCaseEnd) : '';
if (!/createElement\('ol'\)[\s\S]*?setAttribute\('aria-current', 'step'\)/.test(workspaceCaseBody)) {
  failures.push('Workspace assistance progress must use a semantic ordered list with aria-current.');
}
if (!/caseData\?\.resume_available === true && validWorkspaceAgentRunId\(caseData\?\.source\?\.run_id\)/.test(app)
  || !/if \(quoteCaseCanResume\(caseData\) && !storeAgentRunSession\(caseData\.source\.run_id\)\)/.test(workspaceCaseBody)) {
  failures.push('Workspace assistance may resume only a server-enabled case with a valid UUID run id.');
}
if (!/quoteCaseTerminalStatuses\.has\(caseData\.status\)[\s\S]*?\.slice\(0, 12\)/.test(app)
  || !/if \(!terminal\) actions\.append\(handoff\)/.test(workspaceCaseBody)) {
  failures.push('Workspace assistance must retain at most twelve recent terminal cases without an active handoff claim.');
}
if (!/function isConfirmedQuoteCaseRecovery\([\s\S]*?Number\(nextCase\.version\) <= Number\(previousCase\.version\)[\s\S]*?previousCase\.status === 'needs_information'/.test(app)) {
  failures.push('Workspace recovery motion must require a higher server version from a blocked case.');
}
if (!/workspaceQuoteCaseRuntime\.authRequired[\s\S]*?error\?\.status === 401 \|\| error\?\.status === 403[\s\S]*?reauth_required/.test(app)
  || !/if \(!workspaceQuoteCaseRuntime\.authRequired\) scheduleWorkspaceQuoteCasePoll/.test(app)) {
  failures.push('Workspace assistance polling must enter a terminal re-authentication state on 401/403.');
}
if (!/workspaceQuoteCaseMutationRegistry\.set\(mutationCaseId, \{idempotencyKey\}\)[\s\S]*?await reconcileWorkspaceQuoteCases\(\)[\s\S]*?workspaceQuoteCaseMutationRegistry\.delete\(mutationCaseId\)/.test(workspaceCaseBody)) {
  failures.push('A workspace assistance 409 must reconcile the list while its handoff action remains serialized.');
}
const workspaceTimeoutStart = workspaceCaseBody.indexOf("if (error?.code === 'quote_case_handoff_timeout')");
const workspaceConflictStart = workspaceCaseBody.indexOf("} else if (error?.status === 409)", workspaceTimeoutStart);
const workspaceTimeoutBody = workspaceTimeoutStart >= 0 && workspaceConflictStart > workspaceTimeoutStart
  ? workspaceCaseBody.slice(workspaceTimeoutStart, workspaceConflictStart)
  : '';
if (!/requestWithDeadline\([\s\S]*?'quote_case_handoff_timeout'/.test(app)
  || !workspaceTimeoutBody.includes('await reconcileWorkspaceQuoteCases()')
  || workspaceTimeoutBody.includes('delete handoff.dataset.idempotencyKey')) {
  failures.push('QuoteCase handoff must time out after the shared deadline, reconcile, and retain its idempotency key when outcome is ambiguous.');
}
if (!/const workspaceQuoteCaseRetryKeys = new Map\(\)/.test(app)
  || !/retainedIdempotencyKey[\s\S]*?handoff\.dataset\.idempotencyKey/.test(workspaceCaseBody)
  || !/function mergeWorkspaceQuoteCases\([\s\S]*?workspaceQuoteCasePrecedes/.test(app)
  || !/function reconcileWorkspaceQuoteCases\([\s\S]*?reconcileWaiters\.push/.test(app)) {
  failures.push('QuoteCase handoff keys and monotonic case versions must survive card replacement and overlapping list polls.');
}
if (!/quoteCaseCanResume\(caseData\) && !storeAgentRunSession\(caseData\.source\.run_id\)[\s\S]*?return;[\s\S]*?window\.location\.assign/.test(workspaceCaseBody)) {
  failures.push('Saved QuoteCase resume must not navigate when private session storage fails.');
}
if (!/attentionTransitionIds\.add\(caseData\.case_id\)/.test(app)
  || !/terminalTransitionIds\.add\(caseData\.case_id\)/.test(app)
  || !/attentionCase[\s\S]*?מידע נוסף[\s\S]*?terminalCase[\s\S]*?מצב סיום מאומת/.test(app)) {
  failures.push('Existing QuoteCase transitions into attention or terminal state need calm traveler-facing confirmed announcements.');
}
if (!/newlyPositive = !previous && \['queued', 'in_review', 'ready_for_assistance'\]/.test(app)) {
  failures.push('Only newly confirmed active assistance cases may receive positive addition motion.');
}

const resetStart = app.indexOf('function resetAgentRuntime(');
const resetEnd = app.indexOf('\nfunction agentRestBase(', resetStart);
const resetBody = resetStart >= 0 && resetEnd > resetStart ? app.slice(resetStart, resetEnd) : '';
if (!/invalidateAgentPoll\(\);[\s\S]*?invalidateQuoteCasePoll\(\);/.test(resetBody)) {
  failures.push('Resetting Agent runtime must abort and invalidate both polling lifecycles before replacing state.');
}
if (!/agentRuntime\.pollController\?\.abort\(\)[\s\S]*?pollGeneration \+= 1/.test(app)
  || !/agentRuntime\.quoteCasePollController\?\.abort\(\)[\s\S]*?quoteCasePollGeneration \+= 1/.test(app)) {
  failures.push('Agent and quote-case invalidation must abort their request and advance a monotonic generation.');
}

const renderCaseStart = app.indexOf('function renderAgentQuoteCase(');
const renderCaseEnd = app.indexOf('\nasync function fetchQuoteCase(', renderCaseStart);
const renderCaseBody = renderCaseStart >= 0 && renderCaseEnd > renderCaseStart ? app.slice(renderCaseStart, renderCaseEnd) : '';
if (!/currentCase\?\.case_id === caseData\.case_id[\s\S]*?incomingVersion < currentVersion\) return false;/.test(renderCaseBody)) {
  failures.push('Rendering must reject a lower server version for the same quote case before status or actions can regress.');
}

const pollStart = app.indexOf('async function pollAgentQuoteCase(root)');
const pollEnd = app.indexOf('\nfunction renderAgentRun(', pollStart);
const pollBody = pollStart >= 0 && pollEnd > pollStart ? app.slice(pollStart, pollEnd) : '';
if (!pollBody || !/if \(added > 0 && !hasMore\) \{[\s\S]*?await fetchQuoteCase\(caseId, requestOptions\)/.test(pollBody)) {
  failures.push('Polling must drain has_more event pages before refreshing the full case.');
}
if (/if \(added > 0\) \{[\s\S]*?await fetchQuoteCase\(caseId(?:, requestOptions)?\)/.test(pollBody)) {
  failures.push('Polling must not refresh the full case before the event backlog is drained.');
}
if (!/const added = mergeAndRenderQuoteCaseEvents[\s\S]*?if \(added > 0 && !hasMore\)/.test(pollBody)) {
  failures.push('An empty event poll must not trigger a full quote-case request.');
}
if (!/const retryDelay = Math\.min\(120000,[\s\S]*?scheduleQuoteCasePoll\(root, retryDelay\)/.test(pollBody)
  || !/scheduleQuoteCasePoll\(root, hasMore \? 250 : 12000\)/.test(pollBody)) {
  failures.push('Active quote-case polling must use bounded retry backoff and fast has_more pagination.');
}
if (!/quoteCasePollGeneration \+ 1[\s\S]*?quoteCasePollCaseToken = caseId[\s\S]*?quoteCasePollController = controller[\s\S]*?quoteCasePollInFlight = true/.test(pollBody)
  || (pollBody.match(/if \(!isCurrentQuoteCasePoll\(caseId, generation, controller\)\) return;/g) || []).length < 4
  || !/catch \(error\) \{\s*if \(!isCurrentQuoteCasePoll\(caseId, generation, controller\)\) return;/.test(pollBody)
  || !/finally \{[\s\S]*?if \(!isCurrentQuoteCasePoll\(caseId, generation, controller\)\) return;[\s\S]*?quoteCasePollInFlight = false;[\s\S]*?quoteCasePollController = null;[\s\S]*?quoteCasePollCaseToken = '';/.test(pollBody)) {
  failures.push('Quote-case polling must guard every asynchronous mutation and cleanup with its case, generation, and controller.');
}
if (!/const caseToken = agentRuntime\.quoteCase\.case_id;[\s\S]*?const generation = agentRuntime\.quoteCasePollGeneration;[\s\S]*?quoteCasePollGeneration !== generation\) return;/.test(app)) {
  failures.push('Quote-case timer callbacks must reject stale case or generation tokens.');
}

const agentPollStart = app.indexOf('async function pollAgentRun(root)');
const agentPollEnd = app.indexOf('\nasync function createAgentRun(', agentPollStart);
const agentPollBody = agentPollStart >= 0 && agentPollEnd > agentPollStart ? app.slice(agentPollStart, agentPollEnd) : '';
if (!/pollGeneration \+ 1[\s\S]*?pollRunToken = runId[\s\S]*?pollController = controller[\s\S]*?pollInFlight = true/.test(agentPollBody)
  || (agentPollBody.match(/if \(!isCurrentAgentPoll\(runId, generation, controller\)\) return;/g) || []).length < 4
  || !/catch \(error\) \{\s*if \(!isCurrentAgentPoll\(runId, generation, controller\)\) return;/.test(agentPollBody)
  || !/finally \{[\s\S]*?if \(!isCurrentAgentPoll\(runId, generation, controller\)\) return;[\s\S]*?pollInFlight = false;[\s\S]*?pollController = null;[\s\S]*?pollRunToken = '';/.test(agentPollBody)) {
  failures.push('AgentRun polling must guard every asynchronous mutation and cleanup with its run, generation, and controller.');
}
if (!/const runToken = agentRuntime\.runId;[\s\S]*?const generation = agentRuntime\.pollGeneration;[\s\S]*?pollGeneration !== generation\) return;/.test(app)
  || !/function reconnectAgentPolling\(root\) \{[\s\S]*?invalidateAgentPoll\(\);[\s\S]*?pollAgentRun\(root\);/.test(app)) {
  failures.push('AgentRun timers and manual reconnect must invalidate stale request lifecycles.');
}
if (!/const reconnectRun = Boolean[\s\S]*?const reconnectCase = Boolean[\s\S]*?invalidateAgentPoll\(\);[\s\S]*?invalidateQuoteCasePoll\(\);[\s\S]*?document\.visibilityState !== 'visible'/.test(app)) {
  failures.push('Visibility reconnect must abort and invalidate old Agent and quote-case requests before rescheduling.');
}
if (!/while \(agentRuntime\.quoteCaseEvents\.length > quoteCaseEventDomLimit\)[\s\S]*?quoteCaseEvents\.shift\(\)[\s\S]*?firstElementChild\?\.remove\(\)/.test(app)) {
  failures.push('Quote-case event history must cap both browser state and rendered DOM at 100 entries.');
}
const forwardStart = app.indexOf('function isConfirmedQuoteCaseForward(');
const forwardEnd = app.indexOf('\nfunction renderQuoteCaseProgress(', forwardStart);
const forwardBody = forwardStart >= 0 && forwardEnd > forwardStart ? app.slice(forwardStart, forwardEnd) : '';
if (!forwardBody
  || !/Number\(nextCase\.version\) <= Number\(previousCase\.version\)/.test(forwardBody)
  || !/nextCase\.status === 'needs_information'/.test(forwardBody)
  || !/Number\(rank\[nextCase\.status\] \|\| 0\) > Number\(rank\[previousCase\.status\] \|\| 0\)/.test(forwardBody)
  || !/if \(isConfirmedQuoteCaseForward\(previousCase, caseData\)\) \{[\s\S]*?classList\.add\('is-advancing'\)/.test(app)) {
  failures.push('Case advancement motion must require a higher server version and a genuine forward, non-blocked status transition.');
}

requireMarkers(css, 'Quote-case presentation', [
  '.agent-quote-progress li.is-current',
  '.agent-quote-progress li.is-completed',
  '.agent-quote-event',
  '.agent-quote-actions p[data-state="running"]',
  '.agent-quote-actions p[data-state="success"]',
  '.workspace-quote-action-status[data-state="reused"]',
  '@keyframes agent-action-confirm',
  '.workspace-quote-grid',
  '.workspace-quote-card-progress li[data-state="current"]',
  '@media (prefers-reduced-motion: reduce)',
]);
if (!/@media \(max-width: 680px\)[\s\S]*?\.workspace-quote-grid \{ grid-template-columns: 1fr; \}/.test(css)) {
  failures.push('Workspace quote cases must become a vertical card list on mobile.');
}
if (!/@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.agent-quote-case\.is-advancing[\s\S]*?animation: none !important;/.test(css)) {
  failures.push('Agent quote-case motion needs a reduced-motion path.');
}
if (!/@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.agent-quote-actions p\[data-state="running"\]::before[\s\S]*?animation: none !important;/.test(css)) {
  failures.push('Planner handoff feedback needs a reduced-motion path.');
}
if (!/@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.workspace-quote-action-status\[data-state="running"\]::before[\s\S]*?animation: none !important;/.test(css)) {
  failures.push('Workspace handoff feedback needs a reduced-motion path.');
}

if (failures.length) {
  console.error('Tra-Vel quote-case theme validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel quote-case theme validation passed (planner, workspace, exact REST writes, truthful progress, reduced motion).');
