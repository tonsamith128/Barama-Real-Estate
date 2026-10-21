/* Barama One-click Updater: verified read-only status, safe progress messages, and server-rendered readiness refresh. */
(function () {
  'use strict';
  const host = document.querySelector('[data-oneclick-status]');
  if (!host) return;
  const endpoint = host.getAttribute('data-oneclick-endpoint');
  if (!endpoint || !endpoint.startsWith('/') || endpoint.startsWith('//')) return;
  const state = host.querySelector('[data-oneclick-state]');
  const worker = host.querySelector('[data-oneclick-worker]');
  const details = host.querySelector('[data-oneclick-stage-message]');
  const initialWorker = host.getAttribute('data-oneclick-initial-worker-ready');
  const initialPending = host.getAttribute('data-oneclick-initial-pending');
  const initialState = host.getAttribute('data-oneclick-initial-state');
  if (!['0', '1'].includes(initialWorker) || !['0', '1'].includes(initialPending)) return;
  const messages = Object.freeze({
    not_started: 'No update job is active in this administrator session.',
    checking: 'The trusted worker is checking the requested release and update authorization.',
    backing_up: 'The trusted worker is preparing recovery backups before making changes.',
    installing: 'The trusted worker is applying the verified update.',
    verifying: 'The trusted worker is verifying the installation.',
    completed: 'The last update job completed. Confirm the installed version shown above.',
    blocked: 'The last request was blocked by a safety check. No successful installation is claimed.',
    failed: 'The last update job failed. Review the protected recovery state before retrying.',
    recovery_review_required: 'Manual recovery review is required. Do not request another update.',
    unavailable: 'Trusted update status is temporarily unavailable. No installation success is claimed.'
  });
  if (!Object.prototype.hasOwnProperty.call(messages, initialState)) return;
  const states = new Set(Object.keys(messages));
  const terminal = new Set(['completed', 'blocked', 'failed', 'recovery_review_required']);
  let busy = false;
  let reloading = false;
  function showStatus(value) {
    if (!states.has(value)) return;
    if (state) state.textContent = value.replace(/_/g, ' ');
    if (details) details.textContent = messages[value];
  }
  showStatus(initialState);
  async function check() {
    if (busy || reloading || document.hidden) return;
    busy = true;
    try {
      const response = await fetch(endpoint, {
        method: 'GET', credentials: 'same-origin', cache: 'no-store',
        redirect: 'error', headers: { 'Accept': 'application/json' }
      });
      if (!response.ok) return;
      const data = await response.json();
      if (data.schema !== 'barama-oneclick-dashboard-status-v1'
          || !states.has(data.state)
          || typeof data.worker_ready !== 'boolean'
          || typeof data.pending !== 'boolean') return;
      showStatus(data.state);
      if (worker) worker.textContent = data.worker_ready
        ? ' · Trusted worker online' : ' · Trusted worker offline';
      // A signed release and the PHP-rendered button gate are authoritative.
      // Never enable/submit an update or trust status text as install approval.
      const readinessChanged = data.worker_ready !== (initialWorker === '1');
      const queueChanged = data.pending !== (initialPending === '1');
      const finished = terminal.has(data.state) && data.state !== initialState;
      if (readinessChanged || queueChanged || finished) {
        reloading = true;
        window.location.reload();
      }
    } catch (_) { /* A transient failure cannot authorize installation. */ }
    finally { busy = false; }
  }
  window.setInterval(check, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
  check();
})();
