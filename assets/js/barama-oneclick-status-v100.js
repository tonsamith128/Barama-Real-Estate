/* Barama One-click Updater v1.0: read-only job status polling, no installation call. */
(function () {
  'use strict';
  const host = document.querySelector('[data-oneclick-status]');
  if (!host) return;
  const endpoint = host.getAttribute('data-oneclick-endpoint');
  if (!endpoint || !endpoint.startsWith('/')) return;
  const state = host.querySelector('[data-oneclick-state]');
  const worker = host.querySelector('[data-oneclick-worker]');
  let busy = false;
  let last = '';
  const states = new Set(['not_started', 'checking', 'backing_up', 'installing',
    'verifying', 'completed', 'blocked', 'failed', 'recovery_review_required', 'unavailable']);
  async function check() {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const response = await fetch(endpoint, {
        method: 'GET', credentials: 'same-origin', cache: 'no-store',
        redirect: 'error', headers: { 'Accept': 'application/json' }
      });
      if (!response.ok) return;
      const data = await response.json();
      if (data.schema !== 'barama-oneclick-dashboard-status-v1' || !states.has(data.state)) return;
      if (state) state.textContent = data.state.replace(/_/g, ' ');
      if (worker) worker.textContent = data.worker_ready ? ' · Trusted worker online' : ' · Trusted worker offline';
      if (data.state === 'completed' && last !== 'completed') {
        last = 'completed';
        window.location.reload();
      } else {
        last = data.state;
      }
    } catch (_) { /* A temporary failure cannot change installation state. */ }
    finally { busy = false; }
  }
  window.setInterval(check, 7000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
})();
