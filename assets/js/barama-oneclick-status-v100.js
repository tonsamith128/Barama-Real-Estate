/* Barama One-click Updater: read-only status polling and server-rendered readiness refresh. */
(function () {
  'use strict';
  const host = document.querySelector('[data-oneclick-status]');
  if (!host) return;
  const endpoint = host.getAttribute('data-oneclick-endpoint');
  if (!endpoint || !endpoint.startsWith('/') || endpoint.startsWith('//')) return;
  const state = host.querySelector('[data-oneclick-state]');
  const worker = host.querySelector('[data-oneclick-worker]');
  const initialWorker = host.getAttribute('data-oneclick-initial-worker-ready');
  const initialPending = host.getAttribute('data-oneclick-initial-pending');
  const initialState = host.getAttribute('data-oneclick-initial-state');
  if (!['0', '1'].includes(initialWorker) || !['0', '1'].includes(initialPending)) return;
  const states = new Set(['not_started', 'checking', 'backing_up', 'installing',
    'verifying', 'completed', 'blocked', 'failed', 'recovery_review_required', 'unavailable']);
  if (!states.has(initialState)) return;
  const terminal = new Set(['completed', 'blocked', 'failed', 'recovery_review_required']);
  let busy = false;
  let reloading = false;
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
      if (state) state.textContent = data.state.replace(/_/g, ' ');
      if (worker) worker.textContent = data.worker_ready
        ? ' · Trusted worker online' : ' · Trusted worker offline';
      // The Update Now button is gated by PHP using current session feed and
      // worker readiness. Refresh server-rendered controls; never override
      // disabled or submit/install from JavaScript.
      const readinessChanged = data.worker_ready !== (initialWorker === '1');
      const queueChanged = data.pending !== (initialPending === '1');
      const finished = terminal.has(data.state) && data.state !== initialState;
      if (readinessChanged || queueChanged || finished) {
        reloading = true;
        window.location.reload();
      }
    } catch (_) { /* A transient failure cannot enable an update. */ }
    finally { busy = false; }
  }
  window.setInterval(check, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
  check();
})();
