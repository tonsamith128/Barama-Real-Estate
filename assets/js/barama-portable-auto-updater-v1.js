/* Barama customer file-only updater: one user click, three bounded CSRF POSTs.
 * Never invent progress percentages or convert a queued request into success.
 * Without JS each stage remains a normal server-side POST+redirect. */
(() => {
  'use strict';
  const form = document.querySelector('form[data-bau-portable-flow]');
  if (!form) return;
  const button = form.querySelector('button[type=submit]');
  const token = form.querySelector('input[name="_token"]');
  const start = form.getAttribute('data-portable-start');
  if (!button || !token || !/^[a-f0-9]{64}$/.test(token.value)
      || !['not_started', 'downloaded', 'health_pending'].includes(start)) return;
  const stages = [
    ['not_started', '/dashboard/system/auto-updater/portable-update-now', 'downloaded',
      'Downloading and verifying the signed update…'],
    ['downloaded', '/dashboard/system/auto-updater/portable-install', 'health_pending',
      'Creating a private backup and installing verified files…'],
    ['health_pending', '/dashboard/system/auto-updater/portable-finish', 'completed_file_only',
      'Checking the installed version and file integrity…']
  ];
  let active = false;
  form.addEventListener('submit', async (event) => {
    if (active) { event.preventDefault(); return; }
    if (!window.confirm('Proceed with this authenticated file-only update step? Confirm the site has a verified recovery plan.')) { event.preventDefault(); return; }
    const api = ('fetch' in window) && ('FormData' in window);
    if (!api) return; // plain HTML POST remains usable
    event.preventDefault();
    active = true;
    button.disabled = true;
    const original = button.textContent;
    const note = document.createElement('p');
    note.className = 'bau-safe-note';
    note.setAttribute('role', 'status');
    note.setAttribute('aria-live', 'polite');
    form.appendChild(note);
    let state = start;
    try {
      while (state !== 'completed_file_only') {
        const next = stages.find((row) => row[0] === state);
        if (!next) throw new Error('Unknown updater state');
        note.textContent = next[3];
        const endpoint = new URL(next[1], window.location.origin);
        if (endpoint.origin !== window.location.origin) throw new Error('Unsafe endpoint');
        const body = new FormData();
        body.set('_token', token.value);
        const result = await fetch(endpoint.pathname, {
          method: 'POST', credentials: 'same-origin', redirect: 'error',
          cache: 'no-store', body,
          headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        });
        const data = await result.json();
        if (!result.ok || data?.schema !== 'barama-portable-customer-action-v1'
            || data.ok !== true || data.state !== next[2]) {
          throw new Error('Server did not confirm this update stage');
        }
        state = data.state;
      }
      note.textContent = 'File-only update complete. Reloading verified administrator status…';
    } catch (_) {
      note.textContent = 'An updater step stopped. Check the private job status on the refreshed page; do not resubmit without review.';
    } finally {
      // The next page derives status from private server receipts, not JS.
      button.textContent = original;
      window.location.reload();
    }
  });
})();