/* Barama License Center v1.4.4 — prominent, persistent-on-page verification feedback.
 * No purchase code, activation token, or other credential enters browser storage.
 * UI is refreshed from the authenticated server-rendered page after each success.
 */
(() => {
  'use strict';
  const root = document.querySelector('[data-bl-settings]');
  if (!root) return;

  const messages = {
    INVALID_LICENSE_KEY: 'Enter a valid CodeCanyon purchase code or Ton Samith license key.',
    INVALID_PUBLISHER_ORIGIN: 'The trusted Publisher HTTPS endpoint is not configured correctly.',
    LICENSE_SERVER_UNAVAILABLE: 'The Publisher is temporarily unavailable. Your saved license was not changed.',
    LICENSE_SERVICE_UNAVAILABLE: 'The licensing service could not complete the request. Your saved license was not changed.',
    INVALID_PUBLISHER_RESPONSE: 'The Publisher returned an unexpected response.',
    CANONICAL_HTTPS_URL_REQUIRED: 'This installation requires its canonical HTTPS domain.',
    ACTIVATION_REQUIRED: 'A valid activation is required before verification.',
    ACTIVATION_REQUIRED_OR_INVALID: 'Your installed activation needs review. No token was erased.',
    LICENSE_ALREADY_ACTIVE: 'This installation already has an active license. Please use Verify again.',
    LICENSE_REVOKED: 'This license has been revoked by the Publisher.',
    LICENSE_SUSPENDED: 'This license has been suspended by the Publisher.',
    LICENSE_EXPIRED: 'This license has expired.',
    DOMAIN_MISMATCH: 'This license is assigned to another domain.',
    INSTALLATION_MISMATCH: 'This license belongs to another installation.',
    LICENSE_REQUEST_REJECTED: 'The Publisher rejected this request.',
    LICENSE_VERIFICATION_UNAVAILABLE: 'Verification could not be completed. Your existing activation has been preserved.',
    LICENSE_DEACTIVATION_UNAVAILABLE: 'Deactivation could not be confirmed. Your license remains unchanged.',
    LICENSE_ACTIVATION_FAILED: 'The Publisher could not activate that code. Check it and try again.',
    LICENSE_RESPONSE_MISMATCH: 'Publisher verification did not match this installation.',
    CONFLICTING_LICENSE_RECORDS: 'Two activation records need administrator review. No records were changed.',
    EXISTING_LICENSE_NEEDS_REVIEW: 'An existing license requires review. A duplicate activation was not created.',
    ACCESS_DENIED: 'Administrator permission is required for license changes.',
  };
  const blocked = new Set(['LICENSE_REVOKED', 'LICENSE_SUSPENDED', 'LICENSE_EXPIRED']);
  let busy = false;
  let toastTimer = null;

  // The toast is outside the server-rendered section so refreshing that section
  // cannot erase a success alert before the administrator sees it.
  const toast = document.createElement('aside');
  toast.className = 'bl-license-toast';
  toast.hidden = true;
  toast.setAttribute('aria-live', 'polite');
  toast.setAttribute('aria-atomic', 'true');
  toast.setAttribute('role', 'status');
  const symbol = document.createElement('span');
  symbol.className = 'bl-license-toast__icon';
  symbol.setAttribute('aria-hidden', 'true');
  const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  icon.setAttribute('viewBox', '0 0 24 24');
  icon.setAttribute('fill', 'none');
  icon.setAttribute('stroke', 'currentColor');
  icon.setAttribute('stroke-width', '2.5');
  icon.setAttribute('stroke-linecap', 'round');
  icon.setAttribute('stroke-linejoin', 'round');
  const glyph = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  icon.appendChild(glyph);
  symbol.appendChild(icon);
  const copy = document.createElement('div');
  copy.className = 'bl-license-toast__copy';
  const heading = document.createElement('strong');
  heading.className = 'bl-license-toast__title';
  const description = document.createElement('p');
  description.className = 'bl-license-toast__message';
  copy.append(heading, description);
  const dismiss = document.createElement('button');
  dismiss.type = 'button';
  dismiss.className = 'bl-license-toast__close';
  dismiss.setAttribute('aria-label', 'Dismiss notification');
  dismiss.textContent = '×';
  dismiss.addEventListener('click', () => { toast.hidden = true; if (toastTimer) clearTimeout(toastTimer); });
  toast.append(symbol, copy, dismiss);
  document.body.appendChild(toast);

  function notify(title, message, kind = 'info', ms = 11000) {
    if (toastTimer) clearTimeout(toastTimer);
    toast.dataset.kind = kind;
    toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    heading.textContent = title;
    description.textContent = message;
    glyph.setAttribute('d', kind === 'success' ? 'm5 12 4.5 4.5L19 7' : kind === 'error' ? 'M12 8v5m0 4h.01M3 20h18L12 4 3 20Z' : 'M12 3a9 9 0 1 0 9 9M12 7v5l3 2');
    toast.hidden = false;
    if (ms > 0) toastTimer = setTimeout(() => { toast.hidden = true; toastTimer = null; }, ms);
  }

  function showInline(message, error) {
    const feedback = root.querySelector('[data-bl-feedback]');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.dataset.blError = error ? 'true' : 'false';
    feedback.setAttribute('role', error ? 'alert' : 'status');
    feedback.hidden = false;
  }

  function markBlocked(label) {
    root.dataset.blLicenseState = 'error';
    const pill = root.querySelector('[data-bl-status]');
    if (pill) pill.dataset.state = 'error';
    root.querySelectorAll('[data-bl-status-label], [data-bl-table-status], [data-bl-table-state]')
      .forEach(el => { el.textContent = label; });
    const cell = root.querySelector('[data-bl-table-state]');
    if (cell) cell.dataset.state = 'error';
  }

  function toggleDetail(button) {
    const id = button.getAttribute('aria-controls');
    const panel = id ? document.getElementById(id) : null;
    if (!panel || !root.contains(panel)) return;
    const expanding = button.getAttribute('aria-expanded') !== 'true';
    root.querySelectorAll('[data-bl-details-toggle]').forEach(other => {
      const otherId = other.getAttribute('aria-controls');
      const otherPanel = otherId ? document.getElementById(otherId) : null;
      if (otherPanel && root.contains(otherPanel)) otherPanel.hidden = true;
      other.setAttribute('aria-expanded', 'false');
    });
    panel.hidden = !expanding;
    button.setAttribute('aria-expanded', expanding ? 'true' : 'false');
  }

  root.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const details = target?.closest('[data-bl-details-toggle]');
    if (details && root.contains(details)) { toggleDetail(details); return; }
    const reveal = target?.closest('[data-bl-reveal]');
    if (!reveal || !root.contains(reveal)) return;
    const input = root.querySelector('#bl-license-key');
    if (!input) return;
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    reveal.textContent = showing ? 'Show' : 'Hide';
    reveal.setAttribute('aria-label', showing ? 'Show license key' : 'Hide license key');
    input.focus();
  });

  async function refreshFromServer() {
    try {
      const result = await fetch(window.location.href, {
        method: 'GET', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!result.ok || !(result.headers.get('Content-Type') || '').includes('text/html')) return false;
      const markup = await result.text();
      const doc = new DOMParser().parseFromString(markup, 'text/html');
      const updated = doc.querySelector('[data-bl-settings]');
      if (!updated || !updated.hasAttribute('data-bl-license-state')) return false;
      root.innerHTML = updated.innerHTML;
      root.dataset.blLicenseState = updated.dataset.blLicenseState;
      return true;
    } catch (_) { return false; }
  }

  root.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-bl-form]')) return;
    event.preventDefault();
    if (busy) return;
    if (form.dataset.blConfirm && !window.confirm(form.dataset.blConfirm)) return;
    const button = form.querySelector('button[type="submit"]');
    if (!button || button.disabled) return;
    let action = '';
    try { action = new URL(form.action, window.location.href).searchParams.get('action') || ''; } catch (_) {}
    if (!['activate', 'verify', 'deactivate'].includes(action)) return;
    busy = true;
    button.disabled = true;
    const originalLabel = button.innerHTML;
    const verb = action === 'verify' ? 'Verifying' : action === 'activate' ? 'Activating' : 'Deactivating';
    button.textContent = verb + '…';
    const feedback = root.querySelector('[data-bl-feedback]');
    if (feedback) feedback.hidden = true;
    notify(verb + ' license', 'Contacting Ton Samith License Manager. Please wait…', 'info', 0);

    try {
      const response = await fetch(form.action, {
        method: 'POST', credentials: 'same-origin', redirect: 'error', cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      });
      if (!(response.headers.get('Content-Type') || '').includes('application/json')) {
        throw new Error('INVALID_HTTP_RESPONSE');
      }
      const data = await response.json();
      if (!response.ok || data?.ok !== true) {
        const problem = new Error(messages[data?.error] || 'The license request was not accepted. Please try again or contact support.');
        problem.reason = data?.error;
        throw problem;
      }
      const refreshed = await refreshFromServer();
      const title = action === 'verify' ? 'License verified' : action === 'activate' ? 'License activated' : 'Installation deactivated';
      const message = action === 'deactivate'
        ? 'The Publisher confirmed deactivation. License status has been refreshed.'
        : 'The Publisher confirmed this installation. The license is shared with Auto Updater.';
      const notice = refreshed ? message : 'Publisher confirmed the operation. Refresh the page to update the displayed details.';
      notify(title, notice, 'success', 12500);
      showInline(notice, false);
    } catch (error) {
      const reason = typeof error?.reason === 'string' ? error.reason : '';
      const known = reason && messages[reason];
      const explanation = error?.message === 'INVALID_HTTP_RESPONSE'
        ? 'The website returned an unexpected response. Your existing license was not cleared.'
        : known || 'Could not complete the request. The current license was not changed by this page.';
      notify('License action needs attention', explanation, 'error', 17000);
      showInline(explanation, true);
      if (blocked.has(reason)) markBlocked('License blocked');
    } finally {
      // A successful refresh replaced the form, so only restore if it is still connected.
      if (button.isConnected) { button.innerHTML = originalLabel; button.disabled = false; }
      busy = false;
    }
  });
})();
