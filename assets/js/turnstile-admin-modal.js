/* BARAMA_TURNSTILE_MODAL_V113 — explicit token lifecycle, guarded re-verification */
(() => {
  'use strict';
  const overlay = document.getElementById('br-ts-overlay');
  if (!overlay) return;
  const dialog = overlay.querySelector('[role="dialog"]');
  const openers = document.querySelectorAll('[data-br-ts-open]');
  const closers = overlay.querySelectorAll('[data-br-ts-close]');
  const widget = overlay.querySelector('[data-br-ts-widget]');
  const verifyForm = overlay.querySelector('[data-br-ts-verify-form]');
  const tokenField = overlay.querySelector('[data-br-ts-token]');
  const verifyButton = overlay.querySelector('[data-br-ts-verify-button]');
  const hint = overlay.querySelector('[data-br-ts-widget-hint]');
  const reverifyButton = overlay.querySelector('[data-br-ts-reverify]');
  let previouslyFocused = null;
  let widgetId = null;
  let renderTimer = null;
  let scriptWaitAttempts = 0;
  const MAX_SCRIPT_ATTEMPTS = 90;
  function hintText(message) { if (hint) hint.textContent = message; }
  function setToken(token) {
    if (!tokenField) return;
    tokenField.value = typeof token === 'string' ? token : '';
    if (verifyButton) verifyButton.disabled = !tokenField.value;
    if (tokenField.value) hintText('Challenge completed. Click Verify real credentials.');
  }
  function renderChallenge() {
    if (!verifyForm || verifyForm.hidden || !widget || !widget.dataset.sitekey || widgetId !== null) return;
    function finish() {
      if (overlay.hidden || verifyForm.hidden || widgetId !== null) return;
      if (!window.turnstile || typeof window.turnstile.render !== 'function') {
        if (++scriptWaitAttempts > MAX_SCRIPT_ATTEMPTS) {
          hintText('Cloudflare could not load. Close this popup and retry.');
          return;
        }
        renderTimer = window.setTimeout(finish, 150);
        return;
      }
      try {
        widgetId = window.turnstile.render(widget, {
          sitekey: widget.dataset.sitekey,
          action: 'barama_setup',
          'response-field': false,
          callback: token => setToken(token),
          'expired-callback': () => { setToken(''); hintText('Challenge expired. Complete a new challenge.'); },
          'error-callback': () => { setToken(''); hintText('Cloudflare could not verify this challenge. Retry.'); },
          'timeout-callback': () => { setToken(''); hintText('Challenge timed out. Retry.'); },
        });
        if (widgetId === undefined || widgetId === null) {
          widgetId = null;
          hintText('Cloudflare widget could not be initialized.');
        }
      } catch (_) {
        widgetId = null;
        hintText('Unable to display Cloudflare. Close this popup and retry.');
      }
    }
    if (!document.querySelector('script[data-br-ts-cloudflare]')) {
      const script = document.createElement('script');
      script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
      script.async = true;
      script.defer = true;
      script.dataset.brTsCloudflare = '1';
      script.onerror = () => hintText('Cloudflare script could not load. Check your connection.');
      document.head.appendChild(script);
    }
    finish();
  }
  function openModal() {
    if (!overlay.hidden) return;
    previouslyFocused = document.activeElement;
    overlay.hidden = false;
    document.body.classList.add('br-ts-modal-active');
    dialog.focus();
    renderChallenge();
  }
  function closeModal() {
    overlay.hidden = true;
    document.body.classList.remove('br-ts-modal-active');
    if (renderTimer) { clearTimeout(renderTimer); renderTimer = null; }
    if (previouslyFocused && typeof previouslyFocused.focus === 'function') previouslyFocused.focus();
  }
  openers.forEach(button => button.addEventListener('click', e => { e.preventDefault(); openModal(); }));
  closers.forEach(button => button.addEventListener('click', closeModal));
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
  overlay.addEventListener('keydown', e => {
    if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
    if (e.key !== 'Tab') return;
    const tabbable = [...overlay.querySelectorAll('button:not(:disabled), input:not(:disabled), a[href], select:not(:disabled)')]
      .filter(el => el.getClientRects().length > 0 && !el.closest('[hidden]'));
    if (!tabbable.length) { e.preventDefault(); dialog.focus(); return; }
    const first = tabbable[0], last = tabbable[tabbable.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  if (reverifyButton) reverifyButton.addEventListener('click', () => {
    if (!verifyForm) return;
    verifyForm.hidden = false;
    reverifyButton.hidden = true;
    renderChallenge();
  });
  if (verifyForm) verifyForm.addEventListener('submit', event => {
    const token = widgetId !== null && window.turnstile && typeof window.turnstile.getResponse === 'function'
      ? window.turnstile.getResponse(widgetId) : (tokenField?.value || '');
    setToken(token);
    if (!tokenField?.value) {
      event.preventDefault();
      hintText('Complete the Cloudflare challenge before submitting.');
    } else if (verifyButton) verifyButton.disabled = true;
  });
  if (overlay.dataset.initialOpen === '1') openModal();
})();
