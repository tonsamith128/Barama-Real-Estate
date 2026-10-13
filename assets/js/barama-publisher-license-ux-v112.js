/* Barama Publisher v1.1.2. Browser-only, no credentials or hidden license state are read. */
(() => {
  'use strict';
  const host = document.querySelector('[data-publisher-control]');
  if (!host) return;
  const form = host.querySelector('[data-publisher-check-form]');
  const button = host.querySelector('[data-publisher-check-button]');
  const buttonText = host.querySelector('[data-publisher-button-text]');
  const progress = host.querySelector('[data-publisher-progress]');
  const phase = host.querySelector('[data-publisher-phase]');
  const detail = host.querySelector('[data-publisher-detail]');
  const currentState = host.querySelector('[data-publisher-state]');
  const checked = host.querySelector('[data-publisher-checked]');
  const toast = document.querySelector('[data-publisher-toast]');
  const heroTitle = host.querySelector('[data-publisher-hero-title]');
  const heroDetail = host.querySelector('[data-publisher-hero-detail]');
  const summaryState = document.querySelector('[data-publisher-summary]');
  const lifecycle = host.querySelector('[data-publisher-lifecycle]');
  if (!form || !button || !progress || !phase || !detail || !currentState || !checked || !toast) return;
  const heading = toast.querySelector('[data-publisher-toast-title]');
  const description = toast.querySelector('[data-publisher-toast-description]');
  const symbol = toast.querySelector('[data-publisher-toast-symbol]');
  const dismiss = toast.querySelector('[data-publisher-toast-close]');
  let busy = false;
  let dismissTimer;
  let pendingController;
  const messages = Object.freeze({
    'Active license verified': 'The publisher confirmed an active Barama license bound to this installation. Update entitlement is not yet checked.',
    'Unable to verify': 'The publisher did not confirm a matching active license. Check the publisher connection and license record; no credentials were changed.',
    'License not configured': 'No usable activation token or installation ID is recorded on this installation.',
    'License binding mismatch': 'The local license does not match the expected product, domain, or installation.',
    'Publisher settings not trusted': 'The configured publisher endpoint or product identifier does not match the trusted Barama settings.',
    'Wait before retrying': 'Please wait before another publisher request. Repeated checks are limited to one every 90 seconds.',
    'Publisher temporarily unavailable': 'The publisher is temporarily unavailable. No license credentials were changed.',
    'Publisher confirmed revoked': 'The publisher confirmed this license was revoked for the matching installation.',
    'Publisher confirmed suspended': 'The publisher confirmed this license is suspended.',
    'Publisher confirmed expired': 'The publisher confirmed this license has expired.'
  });
  function notify(state, message) {
    clearTimeout(dismissTimer);
    const isSuccess = state === 'Active license verified';
    const isWarning = state === 'Wait before retrying' || state === 'Publisher temporarily unavailable';
    const tone = isSuccess ? 'success' : isWarning ? 'warning' : 'error';
    toast.dataset.tone = tone;
    toast.setAttribute('role', isSuccess ? 'status' : 'alert');
    heading.textContent = isSuccess ? 'License verified' : isWarning ? 'Please wait to retry' : 'License verification needs attention';
    description.textContent = String(message || messages[state] || messages['Unable to verify']);
    symbol.textContent = isSuccess ? '✓' : isWarning ? '!' : '×';
    toast.hidden = false;
    dismissTimer = setTimeout(() => { toast.hidden = true; }, 10000);
  }
  dismiss?.addEventListener('click', () => { clearTimeout(dismissTimer); toast.hidden = true; });
  if (host.dataset.publisherFlash === '1' && Object.hasOwn(messages,currentState.textContent.trim())) {
    const state = currentState.textContent.trim();
    notify(state, messages[state]);
  }
  function stage(name) {
    if (!lifecycle) return;
    lifecycle.hidden = false;
    lifecycle.querySelectorAll('[data-lifecycle-stage]').forEach(item => {
      item.dataset.active = item.getAttribute('data-lifecycle-stage') === name ? '1' : '0';
    });
  }
  function setPhase(name, explanation) {
    phase.textContent = name;
    detail.textContent = explanation;
  }
  function showBusy() {
    busy = true;
    button.disabled = true;
    button.dataset.busy = '1';
    buttonText.textContent = 'Checking license…';
    progress.hidden = false;
    host.dataset.checking = '1';
    stage('prepare');
    setPhase('Preparing secure request', 'Confirming your administrator session and request.');
  }
  function stopBusy() {
    busy = false;
    button.disabled = false;
    button.dataset.busy = '0';
    buttonText.textContent = host.dataset.verified === '1' ? 'Verify again' : 'Check license';
    progress.hidden = true;
    host.dataset.checking = '0';
  }
  async function submitCheck(event) {
    if (busy) { event.preventDefault(); return; }
    if (!window.fetch || !window.FormData || !window.AbortController) return; // native form fallback
    event.preventDefault();
    showBusy();
    const csrfFields = new FormData(form);
    const abort = new AbortController();
    pendingController = abort;
    const timeout = setTimeout(() => abort.abort(), 30000);
    try {
      // These stages reflect browser activity; they do not imply publisher-side milestones.
      await new Promise(resolve => requestAnimationFrame(resolve));
      stage('publisher');
      setPhase('Contacting Ton Samith License Manager', 'Sending your protected validation request.');
      const promise = fetch(form.action, {
        method: 'POST', credentials: 'same-origin', body: csrfFields,
        redirect: 'error',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: abort.signal
      });
      setPhase('Waiting for publisher response', 'This may take a few seconds. No update will be downloaded or installed.');
      const response = await promise;
      stage('response');
      setPhase('Processing verification result', 'Reading the publisher validation status safely.');
      if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) {
        throw new Error('INVALID_RESPONSE');
      }
      const data = await response.json();
      if (!data || typeof data.state !== 'string' || !Object.hasOwn(messages,data.state)) {
        throw new Error('INVALID_RESPONSE');
      }
      const at = typeof data.checked_at === 'string' ? data.checked_at : 'Just now';
      const nextMessage = typeof data.message === 'string' && data.message.length < 450 ? data.message : messages[data.state];
      currentState.textContent = data.state;
      if (summaryState) summaryState.textContent = data.state;
      host.dataset.verified = data.state === 'Active license verified' ? '1' : '0';
      if (heroTitle) heroTitle.textContent = host.dataset.verified === '1' ? 'License verified' : 'License verification needs attention';
      if (heroDetail) heroDetail.textContent = nextMessage;
      stage('complete');
      checked.textContent = 'Last checked: ' + at;
      currentState.classList.remove('bsys-state--good','bsys-state--warn','bsys-state--neutral');
      currentState.classList.add(data.state === 'Active license verified' ? 'bsys-state--good' : data.state === 'Wait before retrying' ? 'bsys-state--warn' : 'bsys-state--neutral');
      const table = document.querySelector('[data-publisher-readiness-state]');
      if (table) table.textContent = data.state;
      notify(data.state, nextMessage);
    } catch (error) {
      let explanation = 'Verification could not be completed in this browser. Refresh and confirm your administrator session before trying again.';
      if (error && error.name === 'AbortError') explanation = 'The response took too long. The server may still be completing the check; refresh to see the latest status before retrying.';
      if (heroTitle) heroTitle.textContent = 'Verification not completed';
      if (heroDetail) heroDetail.textContent = explanation;
      stage('complete');
      notify('Unable to verify', explanation);
    } finally {
      clearTimeout(timeout);
      pendingController = undefined;
      stopBusy();
    }
  }
  form.addEventListener('submit', submitCheck);
  window.addEventListener('pagehide', () => { pendingController?.abort(); });
})();
