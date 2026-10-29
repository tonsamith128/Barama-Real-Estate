/* BARAMA REAL ESTATE — ADMIN JS — stable area bundle. */
(()=>{
"use strict";
const modules=Object.create(null);

/* ===== license-settings ===== */
modules["license-settings"]=function(__baramaScript){
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

};

/* ===== branding-file-name ===== */
modules["branding-file-name"]=function(__baramaScript){
/* Progressive enhancement for website-identity file selection; no inline handlers. */
(function () {
  'use strict';
  document.addEventListener('change', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-barama-branding-file]')) return;
    const card = input.closest('.barama-branding-card-v104');
    const label = card && card.querySelector('.barama-branding-file-name-v104');
    if (label) label.textContent = input.files && input.files.length ? input.files[0].name : 'Current image retained';
  });
})();

};

/* ===== workspace-confirm ===== */
modules["workspace-confirm"]=function(__baramaScript){
/* No inline event handlers: delegate confirmations for workspace actions. */
(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    const target = event.target;
    const button = target && typeof target.closest === 'function'
      ? target.closest('[data-barama-confirm]') : null;
    if (!button) return;
    const message = button.getAttribute('data-barama-confirm');
    if (message && !window.confirm(message)) event.preventDefault();
  });
})();

};

/* ===== workspace-sidebar-scroll ===== */
modules["workspace-sidebar-scroll"]=function(__baramaScript){
/* BARAMA_WORKSPACE_INDEPENDENT_SIDEBAR_SCROLL_V115 */
(() => {
    'use strict';

    const init = () => {
        const sidebar = document.querySelector('[data-workspace-sidebar]');
        const nav = sidebar?.querySelector('.workspace-nav');

        if (!(sidebar instanceof HTMLElement) || !(nav instanceof HTMLElement)) {
            return;
        }

        const active = nav.querySelector('a.is-active');
        const pathname = window.location.pathname || '/';
        const workspaceRoot = pathname.startsWith('/dashboard')
            ? 'dashboard'
            : pathname.startsWith('/developer')
                ? 'developer'
                : pathname.startsWith('/account')
                    ? 'account'
                    : 'workspace';
        const storageKey = 'barama-workspace-nav-scroll:' + workspaceRoot;

        const clampScroll = (value) => Math.max(
            0,
            Math.min(
                Number.isFinite(value) ? value : 0,
                Math.max(0, nav.scrollHeight - nav.clientHeight)
            )
        );

        const saveScroll = () => {
            try {
                window.sessionStorage.setItem(storageKey, String(Math.round(nav.scrollTop)));
            } catch (_) {
                // Storage is optional; scrolling still works without it.
            }
        };

        const restoreScroll = () => {
            try {
                const stored = Number.parseFloat(
                    window.sessionStorage.getItem(storageKey) || ''
                );

                if (Number.isFinite(stored)) {
                    nav.scrollTop = clampScroll(stored);
                }
            } catch (_) {
                // Ignore unavailable storage.
            }
        };

        const ensureActiveVisible = () => {
            if (!(active instanceof HTMLElement)) {
                return;
            }

            const navRect = nav.getBoundingClientRect();
            const activeRect = active.getBoundingClientRect();
            const breathingRoom = 14;

            const above = activeRect.top < navRect.top + breathingRoom;
            const below = activeRect.bottom > navRect.bottom - breathingRoom;

            if (!above && !below) {
                return;
            }

            const target =
                active.offsetTop
                - ((nav.clientHeight - active.offsetHeight) / 2);

            nav.scrollTop = clampScroll(target);
            saveScroll();
        };

        let saveFrame = 0;

        nav.addEventListener('scroll', () => {
            if (saveFrame) {
                window.cancelAnimationFrame(saveFrame);
            }

            saveFrame = window.requestAnimationFrame(() => {
                saveFrame = 0;
                saveScroll();
            });
        }, { passive: true });

        nav.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', saveScroll, { passive: true });
        });

        restoreScroll();

        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(ensureActiveVisible);
        });

        window.addEventListener('resize', () => {
            window.requestAnimationFrame(ensureActiveVisible);
        }, { passive: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

};

/* ===== workspace-sidebar-tooltips ===== */
modules["workspace-sidebar-tooltips"]=function(__baramaScript){
/* BARAMA_WORKSPACE_RAIL_TOOLTIP_V117A15
 * Admin, Account, Developer: icon label on hover / keyboard focus in desktop rail.
 * Tooltip is portaled to body to avoid sidebar overflow clipping.
 * No route changes, no user content HTML, no change to mobile drawer/toggle state.
 */
(() => {
  'use strict';
  const sidebar = document.querySelector('[data-workspace-sidebar]');
  const nav = sidebar && sidebar.querySelector('.workspace-nav');
  const root = document.documentElement;
  if (!sidebar || !nav) return;
  const desktop = window.matchMedia('(min-width: 992px)');
  let active = null;
  let tooltip = null;
  let mode = '';
  const collapsed = () => desktop.matches && root.classList.contains('workspace-sidebar-is-collapsed');
  const getLink = (target) => {
    if (!(target instanceof Element)) return null;
    const link = target.closest('a[href]');
    return link && nav.contains(link) ? link : null;
  };
  function ensureTooltip() {
    if (tooltip) return tooltip;
    tooltip = document.createElement('div');
    tooltip.id = 'barama-workspace-rail-tooltip-v117a15';
    tooltip.className = 'barama-workspace-rail-tooltip-v117a15';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.appendChild(tooltip);
    return tooltip;
  }
  function restoreTitle(link) {
    if (link && link.hasAttribute('data-barama-rail-saved-title')) {
      const saved = link.getAttribute('data-barama-rail-saved-title');
      if (saved) link.setAttribute('title', saved);
      link.removeAttribute('data-barama-rail-saved-title');
    }
  }
  function hide() {
    if (active) {
      active.removeAttribute('aria-describedby');
      restoreTitle(active);
    }
    active = null;
    mode = '';
    if (tooltip) tooltip.hidden = true;
  }
  function position() {
    if (!active || !tooltip || tooltip.hidden || !collapsed()) { hide(); return; }
    const sidebarRect = sidebar.getBoundingClientRect();
    const rect = active.getBoundingClientRect();
    if (rect.bottom <= sidebarRect.top || rect.top >= window.innerHeight || rect.bottom <= 0) { hide(); return; }
    const ttRect = tooltip.getBoundingClientRect();
    const width = ttRect.width;
    const height = ttRect.height;
    let left = sidebarRect.right + 10;
    if (left + width > window.innerWidth - 10) left = Math.max(10, sidebarRect.left - width - 10);
    const top = Math.max(10, Math.min(window.innerHeight - height - 10, rect.top + (rect.height - height) / 2));
    tooltip.style.left = Math.round(left) + 'px';
    tooltip.style.top = Math.round(top) + 'px';
  }
  function show(link, inputMode) {
    if (!collapsed()) { hide(); return; }
    const label = (link.getAttribute('aria-label') || link.getAttribute('title') || '').trim();
    if (!label) return;
    if (active !== link) hide();
    active = link;
    mode = inputMode;
    if (link.hasAttribute('title') && !link.hasAttribute('data-barama-rail-saved-title')) {
      link.setAttribute('data-barama-rail-saved-title', link.getAttribute('title'));
      link.removeAttribute('title'); // suppress competing native browser tooltip
    }
    const tt = ensureTooltip();
    tt.textContent = label;
    tt.hidden = false;
    link.setAttribute('aria-describedby', tt.id);
    position();
  }
  nav.addEventListener('pointerover', (e) => {
    if (e.pointerType === 'touch') return;
    const link = getLink(e.target);
    if (!link || (active === link && mode === 'pointer')) return;
    show(link, 'pointer');
  });
  nav.addEventListener('pointerout', (e) => {
    if (mode !== 'pointer' || !active) return;
    const previous = getLink(e.target);
    const next = getLink(e.relatedTarget);
    if (previous === active && next !== active) hide();
  });
  nav.addEventListener('focusin', (e) => {
    const link = getLink(e.target);
    if (link) show(link, 'keyboard');
  });
  nav.addEventListener('focusout', (e) => {
    const next = getLink(e.relatedTarget);
    if (active && next !== active) hide();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });
  window.addEventListener('scroll', hide, {passive:true});
  nav.addEventListener('scroll', hide, {passive:true});
  window.addEventListener('resize', hide, {passive:true});
  const toggle = document.querySelector('[data-workspace-sidebar-toggle]');
  if (toggle) toggle.addEventListener('click', hide);
  new MutationObserver(() => { if (!collapsed()) hide(); }).observe(root, {attributes: true, attributeFilter: ['class']});
})();

};

/* ===== change-confirmation ===== */
modules["change-confirmation"]=function(__baramaScript){
/* Barama v1.0.0 canonical JavaScript; original independent IIFEs preserved. */

/* -- change-confirmation-v133.js -- */
/* BARAMA_CHANGE_CONFIRMATION_V133 */
(() => {
    'use strict';

    const body = document.body;
    if (!body || body.dataset.changeConfirmationRequired !== '1') return;

    const endpoint = body.dataset.changeConfirmationEndpoint || '';
    const dialog = document.querySelector('[data-change-confirmation-dialog]');
    const group = dialog?.querySelector('[data-change-confirmation-code]');
    const digits = Array.from(group?.querySelectorAll('[data-change-confirmation-digit]') || []);
    const hidden = group?.querySelector('[data-change-confirmation-passcode]');
    const errorBox = dialog?.querySelector('[data-change-confirmation-error]');
    const approve = dialog?.querySelector('[data-change-confirmation-approve]');
    const cancelButtons = dialog?.querySelectorAll('[data-change-confirmation-cancel]') || [];
    if (!endpoint || !dialog || !group || digits.length !== 6 || !(hidden instanceof HTMLInputElement) || !approve || !errorBox) return;

    let pendingForm = null;
    let pendingSubmitter = null;

    const sync = () => {
        hidden.value = digits.map((input) => input.value.replace(/\D/g, '').slice(0, 1)).join('');
        group.classList.toggle('is-complete', hidden.value.length === 6);
        if (hidden.value.length === 6) group.classList.remove('is-error');
        return hidden.value;
    };

    const clearCode = () => {
        digits.forEach((input) => { input.value = ''; });
        hidden.value = '';
        group.classList.remove('is-complete', 'is-error');
    };

    const fill = (value) => {
        const numeric = String(value || '').replace(/\D/g, '').slice(0, 6);
        digits.forEach((input, index) => { input.value = numeric[index] || ''; });
        sync();
        (digits[Math.min(numeric.length, 5)] || digits[0]).focus();
    };

    digits.forEach((input, index) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(-1);
            sync();
            if (input.value !== '' && index < digits.length - 1) digits[index + 1].focus();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && input.value === '' && index > 0) {
                digits[index - 1].value = '';
                digits[index - 1].focus();
                sync();
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                approve.click();
            }
            if (event.key === 'Escape') close();
        });
    });

    group.addEventListener('paste', (event) => {
        const text = event.clipboardData?.getData('text') || '';
        if (!/\d/.test(text)) return;
        event.preventDefault();
        fill(text);
    });

    const setError = (message = '') => {
        errorBox.textContent = message;
        errorBox.hidden = message === '';
        group.classList.toggle('is-error', message !== '');
    };

    const close = (approved = false) => {
        // BARAMA_SECURITY_CANCEL_SIGNAL_V117A13
        // Only cancellation restores a pending Appearance switch; successful
        // passcode approval deliberately retains the requested state for replay.
        const cancelledForm = approved ? null : pendingForm;
        pendingForm = null;
        pendingSubmitter = null;
        clearCode();
        setError('');
        if (dialog.open) dialog.close();
        if (cancelledForm) {
            cancelledForm.dispatchEvent(new Event('barama:change-confirmation-cancelled', { bubbles: true }));
        }
    };

    const exempt = (form) => {
        if (!(form instanceof HTMLFormElement)) return true;
        if (form.dataset.changeConfirmationExempt === '1') return true;
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') return true;
        const action = new URL(form.action || window.location.href, window.location.href);
        if (action.origin !== window.location.origin) return true;
        return !/(^|\/)(dashboard|developer|account)(\/|$)/.test(action.pathname);
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || exempt(form)) return;
        if (form.dataset.changeConfirmationGranted === '1') {
            delete form.dataset.changeConfirmationGranted;
            return;
        }

        event.preventDefault();
        pendingForm = form;
        // An earlier single-use grant must never leak into a subsequent action.
        const staleGrant = form.querySelector('input[name="change_confirmation"]');
        if (staleGrant instanceof HTMLInputElement) staleGrant.value = '';
        pendingSubmitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        clearCode();
        setError('');
        dialog.showModal();
        window.setTimeout(() => digits[0].focus(), 40);
    }, true);

    cancelButtons.forEach((button) => button.addEventListener('click', () => close()));
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });

    approve.addEventListener('click', async () => {
        if (!pendingForm) return;
        const passcode = sync();
        if (!/^\d{6}$/.test(passcode)) {
            setError('Complete all six passcode digits.');
            digits.find((input) => input.value === '')?.focus() || digits[0].focus();
            return;
        }

        const csrf = pendingForm.querySelector('input[name="_token"]');
        if (!(csrf instanceof HTMLInputElement) || csrf.value === '') {
            setError('This form cannot be verified safely. Refresh the page and try again.');
            return;
        }

        approve.disabled = true;
        setError('');

        try {
            const payload = new URLSearchParams();
            payload.set('_token', csrf.value);
            payload.set('passcode', passcode);
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: payload.toString(),
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data || data.ok !== true) {
                setError(data?.message || 'The change could not be authorized.');
                clearCode();
                digits[0].focus();
                return;
            }

            let grantInput = pendingForm.querySelector('input[name="change_confirmation"]');
            if (!(grantInput instanceof HTMLInputElement)) {
                grantInput = document.createElement('input');
                grantInput.type = 'hidden';
                grantInput.name = 'change_confirmation';
                pendingForm.appendChild(grantInput);
            }
            grantInput.value = String(data.grant || '');
            pendingForm.dataset.changeConfirmationGranted = '1';

            const form = pendingForm;
            const submitter = pendingSubmitter;
            close(true);
            if (typeof form.requestSubmit === 'function') {
                submitter ? form.requestSubmit(submitter) : form.requestSubmit();
            } else {
                form.submit();
            }
        } catch (error) {
            setError('The authorization request failed. Check your connection and try again.');
        } finally {
            approve.disabled = false;
        }
    });
})();


};

/* ===== system-health-theme ===== */
modules["system-health-theme"]=function(__baramaScript){
/* Barama Public System Status — pre-paint theme v1.1.26 */
(() => {
    'use strict';

    const root = document.documentElement;
    let theme = 'light';

    try {
        const stored = window.localStorage.getItem('barama-theme');

        if (stored === 'light' || stored === 'dark') {
            theme = stored;
        } else if (
            window.matchMedia
            && window.matchMedia('(prefers-color-scheme: dark)').matches
        ) {
            theme = 'dark';
        }
    } catch (error) {
        if (
            window.matchMedia
            && window.matchMedia('(prefers-color-scheme: dark)').matches
        ) {
            theme = 'dark';
        }
    }

    root.setAttribute('data-bs-theme', theme);
})();

};

/* ===== barama-auto-updater-unified ===== */
modules["barama-auto-updater-unified"]=function(__baramaScript){
/* Barama unified update lifecycle: in-flight indicator only; no simulated percentage or installation. */
(()=>{'use strict';const root=document.getElementById('barama-unified-auto-updater');if(!root)return;const check=root.querySelector('[data-bau-check-form]');if(check){check.addEventListener('submit',()=>{root.dataset.bauBusy='1';const b=check.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Checking signed publisher…'}const title=root.querySelector('[data-bau-ring-title]');const desc=root.querySelector('[data-bau-ring-desc]');const message=root.querySelector('[data-bau-live-message]');if(title)title.textContent='Checking for updates';if(desc)desc.textContent='Contacting the trusted publisher and validating its response';if(message)message.textContent='The next confirmed result will appear when the publisher responds. No update is being installed.';});}const install=root.querySelector('[data-bau-install-form]');if(install && !install.hasAttribute('data-bau-portable-flow')){install.addEventListener('submit',e=>{if(!window.confirm('Install the publisher-signed update now? The updater will verify authorization and backup affected files before changes. Proceed only if you have a verified recovery plan.')){e.preventDefault();return;}root.dataset.bauBusy='1';const btn=install.querySelector('button[type=submit]');if(btn){btn.disabled=true;btn.textContent='Submitting protected installation…';}})}})();
/* BARAMA_AUTO_UPDATER_EXPERIENCE_V102 — progressive enhancement only.
 * Never changes worker readiness, entitlement, job status, form action, or install button state.
 */
(() => {
  'use strict';
  const root = document.getElementById('barama-unified-auto-updater');
  if (!root || root.dataset.bauExperienceV102 === '1') return;
  root.dataset.bauExperienceV102 = '1';

  // Put the operational workflow first; move existing nodes without recreating
  // forms or changing the server-rendered authorization or state.
  const columns = root.querySelector('.bau-columns');
  const versions = root.querySelector('.bau-versions');
  const readiness = root.querySelector('.bau-readiness');
  const overview = root.querySelector('.bau-bottom');
  if (columns && versions && readiness && overview && columns.parentElement === root) {
    root.insertBefore(versions, overview);
    root.insertBefore(readiness, overview);
  }

  // The existing link remains a standard navigation link; only styling changes.
  if (readiness) {
    const link = readiness.querySelector('a[href]');
    if (link && /\/dashboard\/license(?:[/?#]|$)/.test(link.getAttribute('href') || '')) {
      link.classList.add('bau-button', 'bau-license-action');
      link.textContent = 'Manage license';
    }
  }

  // Turn previous full-width notices into non-blocking, dismissible messages.
  const flashes = Array.from(root.querySelectorAll('.bau-flash'));
  if (flashes.length) {
    const stack = document.createElement('div');
    stack.className = 'bau-feedback-stack';
    stack.setAttribute('aria-label', 'Updater notifications');
    root.appendChild(stack);
    flashes.forEach(flash => {
      const message = (flash.textContent || '').trim().toLowerCase();
      if (/not |unavailable|failed|denied|error|blocked|expired/.test(message)) {
        flash.classList.add('bau-feedback-warning');
      } else if (/no package was downloaded|no newer|not downloaded/.test(message)) {
        flash.classList.add('bau-feedback-info');
      }
      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'bau-feedback-close';
      close.setAttribute('aria-label', 'Dismiss updater notification');
      close.textContent = '×';
      close.addEventListener('click', () => {
        flash.remove();
        if (!stack.children.length) stack.remove();
      });
      flash.appendChild(close);
      stack.appendChild(flash);
    });
  }
})();

};

/* ===== barama-auto-updater-experience ===== */
modules["barama-auto-updater-experience"]=function(__baramaScript){
/* BARAMA_UPDATER_VISIBLE_V103: toast dismiss only, no installation or permission changes. */
(() => {
  'use strict';
  const root = document.getElementById('barama-unified-auto-updater');
  if (!root) return;
  const groups = root.querySelectorAll('.bau-feedback-stack');
  groups.forEach(group => {
    group.querySelectorAll('.bau-flash').forEach(notice => {
      if (notice.querySelector('.bau-feedback-close')) return;
      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'bau-feedback-close';
      close.setAttribute('aria-label', 'Dismiss updater notification');
      close.textContent = '×';
      close.addEventListener('click', () => {
        notice.remove();
        if (!group.querySelector('.bau-flash')) group.remove();
      });
      notice.appendChild(close);
    });
  });
})();

};

/* ===== barama-oneclick-status ===== */
modules["barama-oneclick-status"]=function(__baramaScript){
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

};

/* ===== barama-portable-auto-updater ===== */
modules["barama-portable-auto-updater"]=function(__baramaScript){
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
};

/* ===== barama-updater-completed-receipt ===== */
modules["barama-updater-completed-receipt"]=function(__baramaScript){
/* BARAMA_COMPLETED_UPDATE_RECEIPT_V101 — presentation only.
 * Server verifies the private completed receipt. No update requests or storage writes. */
(() => {
  'use strict';
  const root = document.getElementById('barama-unified-auto-updater');
  const proof = root?.querySelector('[data-bau-verified-completion]');
  if (!root || !proof) return;
  const version = proof.getAttribute('data-bau-verified-completion') || '';
  if (!/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/.test(version)) return;

  // Older screens use a 7-step discovery list; newer screens already
  // show a 4-stage completed summary. Never downgrade the newer summary.
  const list = root.querySelector('.bau-mainpanel .bau-steps');
  const isNextDiscovery = Boolean(list?.textContent?.includes('Signed feed'));
  if (isNextDiscovery) {
    const heading = root.querySelector('.bau-mainpanel .bau-panel-heading h2');
    if (heading) heading.textContent = 'Next update discovery';
    const step = root.querySelector('.bau-mainpanel .bau-panel-heading .bau-pill');
    if (step) step.textContent = 'Ready for next release';
    const ring = root.querySelector('[data-bau-ring-title]');
    if (ring) ring.textContent = 'Up to date · v' + version;
    const description = root.querySelector('[data-bau-ring-desc]');
    if (description) description.textContent = 'Previous signed file-only update completed';
    const note = root.querySelector('[data-bau-live-message]');
    if (note) note.textContent = 'The previous v' + version + ' installation is complete. This panel checks for future releases.';
  }

  // A download-only flash from the earlier POST can outlive final completion.
  const stale = 'Signed ZIP downloaded to private storage; nothing installed yet.';
  for (const notice of root.querySelectorAll('.bau-feedback-stack [role="status"], .bau-feedback-stack .bau-flash')) {
    if (notice.textContent.trim() === stale) {
      notice.textContent = 'Previous file-only update to v' + version + ' completed and its receipt was verified.';
    }
  }
})();

};

/* ===== admin-settings ===== */
modules["admin-settings"]=function(__baramaScript){
/* BARAMA_ADMIN_SETTINGS_WORKSPACE_V120 */
(() => {
    'use strict';

    const root = document.querySelector('[data-admin-settings]');
    if (!root) return;

    const toast = root.querySelector('[data-settings-toast]');

    const dismissToast = () => {
        if (!toast || toast.classList.contains('is-leaving')) return;
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 190);
    };

    toast?.querySelector('[data-settings-toast-close]')?.addEventListener(
        'click',
        dismissToast
    );

    if (toast) {
        window.setTimeout(dismissToast, 4300);
    }

    root.querySelectorAll('[data-settings-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-settings-confirm')
                || 'Continue with this action?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const control = button.closest('.barama-settings-control');
            const input = control?.querySelector('[data-password-field]');
            if (!(input instanceof HTMLInputElement)) return;

            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Hide' : 'View';
        });
    });

    root.querySelectorAll('[data-settings-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const submit = form.querySelector('button[type="submit"]');
            if (!(submit instanceof HTMLButtonElement)) return;

            submit.disabled = true;
            submit.dataset.originalText = submit.textContent || '';
            submit.textContent = 'Saving…';
        });
    });
})();

};

/* ===== admin-email-templates ===== */
modules["admin-email-templates"]=function(__baramaScript){
(() => {
    const root = document.querySelector('[data-email-template-manager][data-email-template-version="142"]');
    if (!root) return;

    const search = root.querySelector('[data-email-template-search]');
    const group = root.querySelector('[data-email-template-group-filter]');
    const rows = Array.from(root.querySelectorAll('[data-email-template-row]'));
    const empty = root.querySelector('[data-email-template-filter-empty]');
    const count = root.querySelector('[data-email-template-visible-count]');

    const apply = () => {
        const needle = (search?.value || '').trim().toLowerCase();
        const selectedGroup = (group?.value || '').trim();
        let visible = 0;

        rows.forEach((row) => {
            const haystack = (row.getAttribute('data-search') || '').toLowerCase();
            const rowGroup = row.getAttribute('data-group') || '';
            const matches = (!needle || haystack.includes(needle))
                && (!selectedGroup || rowGroup === selectedGroup);
            row.hidden = !matches;
            if (matches) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
        if (count) count.textContent = String(visible);
    };

    search?.addEventListener('input', apply);
    group?.addEventListener('change', apply);
    apply();
})();

};

/* ===== barama-system-tool ===== */
modules["barama-system-tool"]=function(__baramaScript){
/* BARAMA_SYSTEM_TOOL_V100: one-use Change Confirmation per protected POST.
 * No passcodes or grants are stored persistently; existing middleware remains authoritative.
 */
(() => {
  'use strict';
  const root = document.getElementById('barama-system-tool');
  if (!root) return;
  const enabled = root.dataset.passcodeEnabled === '1';
  if (!enabled) return; // Server middleware independently enforces account policy.
  const dialog = document.getElementById('btool-passcode-dialog');
  const input = document.getElementById('btool-passcode');
  const feedback = document.getElementById('btool-passcode-feedback');
  const approve = document.getElementById('btool-passcode-submit');
  const cancel = document.getElementById('btool-passcode-cancel');
  if (!dialog || !input || !feedback || !approve || !cancel) return;

  let submitting = false;
  async function requestGrant(csrf, passcode) {
    const body = new FormData();
    body.set('_token', csrf);
    body.set('passcode', passcode);
    const response = await fetch('/security/change-confirmation', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      cache: 'no-store',
      redirect: 'error',
      headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
    });
    const result = await response.json();
    if (!response.ok || result?.ok !== true || !/^[a-f0-9]{48}$/.test(result.grant || '')) {
      throw new Error(response.status === 422
        ? 'Passcode rejected. Check your code and try again.'
        : 'Change Confirmation was not granted. No operation was started.');
    }
    return result.grant;
  }

  function askPasscode() {
    return new Promise((resolve) => {
      feedback.textContent = '';
      input.value = '';
      dialog.showModal();
      input.focus();
      let done = false;
      function finish(value) {
        if (done) return;
        done = true;
        approve.removeEventListener('click', yes);
        cancel.removeEventListener('click', no);
        input.removeEventListener('keydown', enter);
        dialog.removeEventListener('cancel', esc);
        input.value = '';
        if (dialog.open) dialog.close();
        resolve(value);
      }
      const yes = () => {
        const passcode = input.value.trim();
        if (!/^[0-9]{6}$/.test(passcode)) {
          feedback.textContent = 'Enter the six-digit passcode to proceed.';
          return;
        }
        finish(passcode);
      };
      const no = () => finish(null);
      const esc = (event) => { event.preventDefault(); finish(null); };
      const enter = (event) => {
        if (event.key === 'Enter') { event.preventDefault(); yes(); }
      };
      approve.addEventListener('click', yes);
      cancel.addEventListener('click', no);
      input.addEventListener('keydown', enter);
      dialog.addEventListener('cancel', esc);
    });
  }

  for (const form of root.querySelectorAll('form[data-btool-protected]')) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (submitting) return;
      const csrf = form.querySelector('input[name="_token"]')?.value || '';
      if (!/^[a-f0-9]{64}$/.test(csrf)) {
        feedback.textContent = 'Your session has expired. Refresh the page before retrying.';
        return;
      }
      if (form.matches('[data-btool-restore]')) {
        const typed = form.querySelector('input[name="confirmation"]')?.value.trim();
        if (typed !== 'RESTORE DATABASE') {
          feedback.textContent = 'Type RESTORE DATABASE before authorizing a restore.';
          return;
        }
      }
      const submitter = event.submitter;
      const passcode = await askPasscode();
      if (!passcode) return;
      submitting = true;
      try {
        const grant = await requestGrant(csrf, passcode);
        const old = form.querySelector('input[name="change_confirmation"]');
        if (old) old.remove();
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'change_confirmation';
        hidden.value = grant;
        form.appendChild(hidden);
        // Form.submit() excludes the clicked submit button: preserve importer mode.
        if (submitter?.name && submitter.value) {
          const selected = document.createElement('input');
          selected.type = 'hidden';
          selected.name = submitter.name;
          selected.value = submitter.value;
          form.appendChild(selected);
        }
        HTMLFormElement.prototype.submit.call(form); // Existing CSRF + passcode middleware validates it.
      } catch (error) {
        submitting = false;
        input.value = '';
        dialog.showModal();
        feedback.textContent = error instanceof Error ? error.message : 'Request blocked. No change submitted.';
        input.focus();
      }
    });
  }
})();

};

/* ===== barama-demo-premium ===== */
modules["barama-demo-premium"]=function(__baramaScript){
/* BARAMA_DEMO_PREMIUM_V103: layout enhancement, truthful status, screenshot scroll. */
(() => {
  'use strict';
  const root=document.querySelector('#barama-system-tool.bdemo');
  if(!root || root.dataset.premiumV103==='1')return;
  const shell=root.querySelector('.btool-shell'),body=shell?.querySelector('.btool-content');
  if(!shell||!body)return;
  root.dataset.premiumV103='1';root.classList.add('bdemo-v103');shell.classList.add('bdemo-v103-shell');
  // BARAMA_DEMO_FEEDBACK_TOAST_V106: server-rendered, accessible and dismissible.
  const feedbackToast=root.querySelector('[data-demo-feedback-toast]');
  feedbackToast?.querySelector('[data-demo-toast-close]')?.addEventListener('click',()=>feedbackToast.remove());
  root.querySelector('.btool-header')?.classList.add('bdemo-v103-head');
  const mode=root.querySelector('.btool-tab[href*="mode=auto"].is-active')?'auto':'manual';
  const link=root.querySelector('.bdemo-source a'),url=link?.href&&new URL(link.href).hostname==='baramarealestate.tonsamith.com'?link.href:'https://baramarealestate.tonsamith.com/';
  const grid=document.createElement('div');grid.className='bdemo-v103-grid';
  const preview=document.createElement('section');preview.className='bdemo-v103-card bdemo-v103-preview';
  preview.innerHTML='<div class="bdemo-v103-card-head"><span class="bdemo-v103-icon">▣</span><div><h2>Live Demo Preview</h2><p>See the official Barama homepage before importing.</p></div></div><a class="bdemo-v103-shot" target="_blank" rel="noopener noreferrer" aria-label="Open official homepage; hover to scroll the screenshot"><img src="https://baramarealestate.tonsamith.com/assets/images/barama-demo-preview-v103.webp?v=103" loading="eager" decoding="async" alt="Barama Real Estate homepage, listings, projects, companies and footer"><span class="bdemo-v103-preview-label">◉ Preview ↗</span></a><div class="bdemo-v103-action-slot"></div><p class="bdemo-v103-helper"></p>';
  preview.querySelector('.bdemo-v103-shot').href=url;
  // BARAMA_DEMO_READY_PANEL_V108: only server-authorized staging can show install CTA.
  const installReady=root.dataset.demoInstallReady==='1';
  preview.querySelector('.bdemo-v103-helper').textContent=installReady
    ? 'Official ZIP download and verification only. A signed staged package can be reviewed and installed after explicit confirmation below.'
    : 'Official signed ZIP verification and private staging only. Database/media installation is disabled until staging acceptance.';
  const form=Array.from(body.querySelectorAll('form')).find(f=>f.action.endsWith('/dashboard/system/demo-importer/'+mode));
  if(form){preview.querySelector('.bdemo-v103-action-slot').append(form);form.querySelector('.btool-actions')?.classList.add('bdemo-v103-actions');if(mode==='auto')form.querySelector('[type=submit]').textContent='⇩  Download & Verify official ZIP';}
  const oldCard=body.querySelector('.btool-card:not(.bdemo-review):not(.bdemo-api-check)');
  if(oldCard&&!oldCard.querySelector('form'))oldCard.remove();
  const oldHead=body.querySelector('.btool-content-head');if(oldHead)oldHead.hidden=true;
  const good=!!body.querySelector('.bdemo-review'),bad=!!root.querySelector('.btool-flash--error,.bdemo-notice-toast--error');
  if(good && installReady){
    const reviewLink=document.createElement('a');
    reviewLink.className='btool-btn btool-btn-secondary';
    reviewLink.href='#bdemo-verified-package';
    reviewLink.textContent='Review & Install staged demo ↓';
    reviewLink.style.marginTop='10px';
    preview.querySelector('.bdemo-v103-action-slot').append(reviewLink);
  }
  const ring=document.createElement('section');ring.className='bdemo-v103-card bdemo-v103-ring';
  ring.dataset.state=good?'verified':bad?'rejected':'idle';
  const labels=mode==='auto'?['License check','Published package','Download ZIP','Verify signature','Private staging','Review package']:['Select ZIP','Check publisher','Match checksum','Verify signature','Private staging','Review package'];
  const title=good?'Signed ZIP verified':bad?'Verification stopped':'Ready to verify';
  const detail=good?'Server-confirmed signature and private staging. Nothing installed.':bad?'The server did not confirm success. Review the message above.':'Ring motion begins only for a real verification request.';
  const head=document.createElement('div');head.className='bdemo-v103-card-head';head.innerHTML='<span class="bdemo-v103-icon">◉</span><div><h2>Import Progress</h2><p>Server-confirmed verification steps.</p></div>';
  const orbit=document.createElement('div');orbit.className='bdemo-v103-orbit-wrap';
  orbit.innerHTML='<div class="bdemo-v103-orbit"><span class="bdemo-v103-orbit-mark">B</span></div>';
  labels.forEach((label,i)=>{const el=document.createElement('div');el.className='bdemo-v103-step';el.dataset.index=String(i+1);const num=document.createElement('strong');num.textContent=String(i+1).padStart(2,'0');const words=document.createElement('span');words.textContent=label;el.append(num,words);orbit.append(el);});
  const readout=document.createElement('div');readout.className='bdemo-v103-readout';readout.setAttribute('role','status');readout.setAttribute('aria-live','polite');
  readout.innerHTML='<span class="bdemo-v103-readout-dot">●</span><div><strong data-progress-label></strong><p data-progress-detail></p></div>';
  readout.querySelector('[data-progress-label]').textContent=title;readout.querySelector('[data-progress-detail]').textContent=detail;
  ring.append(head,orbit,readout);grid.append(preview,ring);body.prepend(grid);
  // Keep the actual review and confirmation visible BEFORE publisher diagnostics.
  if(good){
    const packageReview=body.querySelector('#bdemo-verified-package');
    if(packageReview)grid.after(packageReview);
  }
  root.querySelector('.bdemo-orbit-panel[data-bdemo-orbit]')?.remove();
  const about=document.createElement('section');about.className='bdemo-v103-bottom';
  const aboutTitle=document.createElement('h2');aboutTitle.textContent='About the Official Barama Live Demo';about.append(aboutTitle);
  const warning=Array.from(root.querySelectorAll('.btool-warning')).find(el=>!el.closest('.bdemo-review'));if(warning)about.append(warning);
  const summary=root.querySelector('.bdemo-summary');if(summary)about.append(summary);shell.after(about);
  const shot=preview.querySelector('.bdemo-v103-shot'),image=shot.querySelector('img');
  const calibrate=()=>{if(!image.naturalWidth)return;const height=image.naturalHeight*shot.clientWidth/image.naturalWidth;shot.style.setProperty('--dp-preview-scroll',Math.max(0,Math.round(height-shot.clientHeight))+'px');};
  image.addEventListener('load',calibrate);if(image.complete)calibrate();window.addEventListener('resize',calibrate,{passive:true});

  /* BARAMA_DEMO_PREVIEW_RECOVERY_V105
   * The 0.9.25 customer ZIP intentionally does not embed the publisher's
   * full-page screenshot. Display its public HTTPS asset from the official
   * demo. If unavailable, show the packaged homepage hero, never a blank box.
   */
  let previewFallback=false;
  image.addEventListener('error',()=>{
    if(previewFallback)return;
    previewFallback=true;
    shot.classList.add('bdemo-preview-fallback-v105');
    image.src='/assets/images/barama-homepage-hero-v1.webp?v=1';
    const notice=document.createElement('span');
    notice.className='bdemo-preview-notice-v105';
    notice.textContent='Full-page preview unavailable. Open the official demo for the live experience.';
    shot.append(notice);
  });
  const steps=Array.from(ring.querySelectorAll('.bdemo-v103-step'));let ticker;
  const reset=()=>{if(ticker)clearInterval(ticker);steps.forEach(x=>x.classList.remove('is-current'));};
  if(form)form.addEventListener('submit',event=>{if(!form.checkValidity())return;window.setTimeout(()=>{
    if(event.defaultPrevented)return;reset();ring.dataset.state='working';
    readout.querySelector('[data-progress-label]').textContent=mode==='auto'?'Downloading and verifying official ZIP':'Checking signed ZIP upload';
    readout.querySelector('[data-progress-detail]').textContent='Request submitted. Labels are a visual guide; final verification comes from the server.';
    let index=0;const tick=()=>{steps.forEach((x,i)=>x.classList.toggle('is-current',i===index));index=(index+1)%steps.length;};
    tick();ticker=window.setInterval(tick,1200);
  },0);});
  // Protected forms use native form.submit() after receiving the grant, so
  // no second submit event fires. Animate *authorization*, never fake imports.
  const dialog=document.getElementById('btool-passcode-dialog');
  const input=document.getElementById('btool-passcode');
  const button=document.getElementById('btool-passcode-submit');
  const feedback=document.getElementById('btool-passcode-feedback');
  const beginAuthorization=()=>{
    if(!form||!dialog?.open||!/^[0-9]{6}$/.test(input?.value.trim()||''))return;
    reset();ring.dataset.state='working';
    readout.querySelector('[data-progress-label]').textContent='Requesting protected authorization';
    readout.querySelector('[data-progress-detail]').textContent='Change Confirmation is being checked. No signed package verification or installation is claimed yet.';
    let index=0;const tick=()=>{steps.forEach((el,i)=>el.classList.toggle('is-current',i===index));index=(index+1)%steps.length;};
    tick();ticker=window.setInterval(tick,1200);
  };
  if(root.dataset.passcodeEnabled==='1'){
    button?.addEventListener('click',beginAuthorization);
    input?.addEventListener('keydown',event=>{if(event.key==='Enter')beginAuthorization();});
    if(feedback)new MutationObserver(()=>{
      if(feedback.textContent.trim()&&ring.dataset.state==='working'){
        reset();ring.dataset.state='rejected';
        readout.querySelector('[data-progress-label]').textContent='Authorization not confirmed';
        readout.querySelector('[data-progress-detail]').textContent='Security check rejected or stopped the request. No content import is claimed.';
      }
    }).observe(feedback,{childList:true,subtree:true,characterData:true});
  }
  window.addEventListener('pageshow',event=>{if(event.persisted){reset();window.location.reload();}});
})();
};

/* ===== barama-demo-manual ===== */
modules["barama-demo-manual"]=function(__baramaScript){
/* BARAMA_DEMO_MANUAL_UI_V104: additive enhancement, preserves v1.0.3 preview and progress ring exactly. */
(() => {
  'use strict';
  const root=document.querySelector('#barama-system-tool.bdemo.bdemo-v103');
  if(!root || root.dataset.manualV104==='1')return;
  const manualTab=root.querySelector('.btool-tab[href*="mode=manual"]');
  if(!manualTab?.classList.contains('is-active'))return;
  const body=root.querySelector('.btool-content');
  const preview=body?.querySelector('.bdemo-v103-preview');
  const ring=body?.querySelector('.bdemo-v103-ring');
  const form=preview?.querySelector('form[action$="/dashboard/system/demo-importer/manual"]');
  const input=form?.querySelector('input[type=file][name=package]');
  const button=form?.querySelector('[type=submit]');
  // Fail gracefully to the original UI if any expected v1.0.3 component drifted.
  if(!body || !preview || !ring || !form || !input || !button)return;
  root.dataset.manualV104='1';root.classList.add('bdemo-manual-v104');
  form.classList.add('bdemo-manual-v104-upload');
  const help=form.querySelector('.btool-help');
  const oldAction=form.querySelector('.btool-actions');
  // Move the same original upload input, CSRF field and submit button. No form
  // clone or change to the endpoint, validation, or Change Confirmation flow.
  const drop=document.createElement('label');drop.className='bdemo-manual-v104-drop';
  drop.innerHTML='<span class="bdemo-manual-v104-icon" aria-hidden="true">⇪</span><span class="bdemo-manual-v104-copy"><strong>Drag & Drop</strong><span>Drop the official publisher ZIP here, or <b>Upload ZIP</b> from your device.</span></span><span class="bdemo-manual-v104-meta">ZIP file only<br>Maximum 64 MB</span>';
  drop.appendChild(input);
  const fileline=document.createElement('div');fileline.className='bdemo-manual-v104-fileline';
  fileline.innerHTML='<strong>Live Demo package</strong><span class="bdemo-manual-v104-filename" aria-live="polite">No file selected</span>';
  const actions=document.createElement('div');actions.className='bdemo-manual-v104-actions';
  if(oldAction)actions.append(...oldAction.childNodes);else actions.append(button);
  const small=document.createElement('p');small.className='bdemo-manual-v104-smallnote';
  small.textContent='The exact signed ZIP is checked against the publisher catalog and staged privately. Uploading does not import website data.';
  // Preserve hidden CSRF/authorization inputs, if present.
  Array.from(form.children).forEach(el=>{if(el!==drop&&el!==actions&&el!==help&&el!==oldAction&&el.tagName==='LABEL')el.remove();});
  if(oldAction)oldAction.remove();
  form.prepend(drop,fileline,actions,small);
  if(help)form.append(help);
  const filename=fileline.querySelector('.bdemo-manual-v104-filename');
  const update=()=>{
    const file=input.files?.[0];
    filename.textContent=file?file.name:'No file selected';
    if(file && (!/\.zip$/i.test(file.name)||file.size>67108864)){
      filename.textContent=file.name+' — select a ZIP under 64 MB';
      input.setCustomValidity('Select a valid ZIP file under 64 MB.');
    } else input.setCustomValidity('');
  };
  input.addEventListener('change',update);update();
  ['dragenter','dragover'].forEach(type=>drop.addEventListener(type,event=>{
    event.preventDefault();event.stopPropagation();drop.classList.add('drag-active');
  }));
  ['dragleave','dragend'].forEach(type=>drop.addEventListener(type,event=>{
    event.preventDefault();event.stopPropagation();drop.classList.remove('drag-active');
  }));
  drop.addEventListener('drop',event=>{
    event.preventDefault();event.stopPropagation();drop.classList.remove('drag-active');
    const file=event.dataTransfer?.files?.[0];
    if(!file || !/\.zip$/i.test(file.name)||file.size>67108864)return;
    try{const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));}
    catch(_){input.focus();} // Browser restrictions: keep click-to-upload intact.
  });
  const workflow=document.createElement('section');
  workflow.className='bdemo-manual-v104-flow';workflow.setAttribute('aria-label','Manual Import Workflow');
  workflow.innerHTML='<div class="bdemo-manual-v104-flowhead"><div><span class="btool-eyebrow">Guided import workflow</span><h2>Manual Import</h2><p>A clear, licensed workflow from the publisher ZIP through verification to a controlled installation review.</p></div><span class="bdemo-manual-v104-chip">3 protected steps</span></div><div class="bdemo-manual-v104-flowgrid"><article class="bdemo-manual-v104-flowitem"><span class="bdemo-manual-v104-flownum">01</span><h3>Download from publisher</h3><p>Obtain the exact published Barama Live Demo package from TonSamith.com.</p><ul class="bdemo-manual-v104-points"><li>Keep the original signed ZIP intact.</li><li>Use the licensed official publisher package.</li></ul></article><article class="bdemo-manual-v104-flowitem"><span class="bdemo-manual-v104-flownum">02</span><h3>Upload and verify</h3><p>Use Drag & Drop above. The server verifies publisher signature, checksum, format and media references.</p><ul class="bdemo-manual-v104-points"><li>Verification is server-side.</li><li>Only a valid ZIP is privately staged.</li></ul></article><article class="bdemo-manual-v104-flowitem"><span class="bdemo-manual-v104-flownum">03</span><h3>Review and import</h3><p>Inspect verified package details and the planned import scope before installing on an approved fresh site.</p><ul class="bdemo-manual-v104-points"><li>Installation is currently disabled.</li><li>Staging backup and rollback acceptance required.</li></ul></article></div>';
  const oldGuide=body.querySelector('.btool-two');
  const review=body.querySelector('.bdemo-review');
  if(oldGuide)oldGuide.replaceWith(workflow);
  else if(review)body.insertBefore(workflow,review);
  else body.append(workflow);
  // Preview and Progress Ring are the existing v1.0.3 elements, not rebuilt.
})();

};

/* ===== global-location-cascade ===== */
modules["global-location-cascade"]=function(__baramaScript){
/* BARAMA_GLOBAL_LOCATION_CASCADE_V110 */
(() => {
  'use strict';
  const humanize = (value) => String(value || 'Area').replace(/[_-]+/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase());
  const roots = Array.from(document.querySelectorAll('[data-location-cascade]'));
  if (!roots.length) return;

  const requestJson = async (url) => {
    const response = await fetch(url, {headers:{'Accept':'application/json'}, credentials:'same-origin'});
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) throw new Error(data.message || `Location request failed (${response.status}).`);
    return data;
  };

  roots.forEach((root) => {
    const endpoint = root.dataset.locationEndpoint || '/location-options';
    const country = root.querySelector('[data-location-country]');
    const value = root.querySelector('[data-location-value]');
    const holder = root.querySelector('[data-location-selects]');
    const hint = root.querySelector('[data-location-hint]');
    const selectedId = Number(root.dataset.selectedLocation || 0);
    if (!country || !value || !holder) return;

    const setLoading = (on) => root.classList.toggle('is-loading', on);
    const setMessage = (message, error = false) => {
      if (!hint) return;
      hint.textContent = message;
      hint.classList.toggle('barama-location-cascade__error', error);
    };
    const clearLevels = (from = 0) => {
      Array.from(holder.querySelectorAll('[data-location-level]')).forEach((label, index) => {
        if (index >= from) label.remove();
      });
    };
    const labelFor = (rows, depth) => {
      const types = [...new Set(rows.map((row) => humanize(row.type)).filter(Boolean))];
      if (types.length === 1) return types[0];
      if (depth === 0) return 'State / Province / Region';
      if (depth === 1) return 'District / County / Area';
      return `Local area ${depth + 1}`;
    };

    const loadChildren = async (countryCode, parentId, depth, selectedChild = 0) => {
      const params = new URLSearchParams({country: countryCode});
      if (parentId) params.set('parent_id', String(parentId));
      const data = await requestJson(`${endpoint}?${params.toString()}`);
      const rows = Array.isArray(data.locations) ? data.locations : [];
      if (!rows.length) return null;
      const label = document.createElement('label');
      label.dataset.locationLevel = String(depth);
      const span = document.createElement('span');
      span.textContent = labelFor(rows, depth);
      const select = document.createElement('select');
      select.className = 'form-select';
      select.dataset.locationSelect = '1';
      const blank = document.createElement('option');
      blank.value = '';
      blank.textContent = 'Select area';
      select.appendChild(blank);
      rows.forEach((row) => {
        const option = document.createElement('option');
        option.value = String(row.id);
        option.textContent = `${row.name} · ${humanize(row.type)}`;
        if (Number(row.id) === Number(selectedChild)) option.selected = true;
        select.appendChild(option);
      });
      label.append(span, select);
      holder.appendChild(label);
      select.addEventListener('change', async () => {
        clearLevels(depth + 1);
        const id = Number(select.value || 0);
        value.value = id > 0 ? String(id) : (parentId ? String(parentId) : '');
        if (!id) return;
        try {
          setLoading(true);
          await loadChildren(countryCode, id, depth + 1, 0);
          setMessage('Location selected. Choose a deeper area if available.');
        } catch (error) {
          setMessage(error.message || 'Unable to load locations.', true);
        } finally { setLoading(false); }
      });
      return select;
    };

    const populateCountries = (rows, preferred = '') => {
      const existingBlank = country.querySelector('option[value=""]')?.textContent || 'No location';
      country.innerHTML = '';
      const blank = document.createElement('option');
      blank.value = '';
      blank.textContent = existingBlank;
      country.appendChild(blank);
      rows.forEach((row) => {
        const option = document.createElement('option');
        option.value = String(row.country_code || '');
        option.textContent = row.name + (Number(row.is_default) === 1 ? ' · Default' : '') + (Number(row.enabled) !== 1 ? ' · Current (disabled)' : '');
        if (option.value === preferred) option.selected = true;
        country.appendChild(option);
      });
    };

    const initialize = async () => {
      try {
        setLoading(true);
        const initial = selectedId > 0
          ? await requestJson(`${endpoint}?location_id=${encodeURIComponent(String(selectedId))}`)
          : await requestJson(endpoint);
        const countries = Array.isArray(initial.countries) ? initial.countries : [];
        const selection = initial.selection && typeof initial.selection === 'object' ? initial.selection : null;
        const selectedCountry = selection?.country_code || '';
        populateCountries(countries, selectedCountry);
        clearLevels(0);
        if (selection && Array.isArray(selection.path)) {
          let parent = 0;
          for (let depth = 0; depth < selection.path.length; depth += 1) {
            const node = selection.path[depth];
            await loadChildren(selectedCountry, parent || null, depth, Number(node.id));
            parent = Number(node.id);
          }
          value.value = String(selectedId);
          if (parent) await loadChildren(selectedCountry, parent, selection.path.length, 0);
        }
      } catch (error) {
        setMessage(error.message || 'Unable to initialize locations.', true);
      } finally { setLoading(false); }
    };

    country.addEventListener('change', async () => {
      clearLevels(0);
      value.value = '';
      const code = country.value;
      if (!code) return;
      try {
        setLoading(true);
        await loadChildren(code, null, 0, 0);
        setMessage('Choose the deepest available administrative area.');
      } catch (error) {
        setMessage(error.message || 'Unable to load locations.', true);
      } finally { setLoading(false); }
    });

    initialize();
  });
})();

};

/* ===== admin-email-template-builder ===== */
modules["admin-email-template-builder"]=function(__baramaScript){
/* BARAMA_ADMIN_EMAIL_TEMPLATE_BUILDER_V143 */
(() => {
    'use strict';

    const root = document.querySelector('[data-email-template-builder][data-email-template-version="143"]');
    if (!root) return;

    const form = root.querySelector('[data-email-builder-form]');
    const nameField = root.querySelector('[data-email-name]');
    const description = root.querySelector('[data-email-description]');
    const subject = root.querySelector('[data-email-subject]');
    const html = root.querySelector('[data-email-html]');
    const rich = root.querySelector('[data-email-rich-editor]');
    const text = root.querySelector('[data-email-text]');
    const customKeyField = root.querySelector('[data-email-custom-key]');
    const createForm = root.querySelector('[data-email-create-form]');
    const createMode = root.getAttribute('data-email-create-mode') === '1';
    const sampleNode = root.querySelector('[data-email-template-samples]');
    const defaultsNode = root.querySelector('[data-email-template-defaults]');
    const previewSubject = root.querySelector('[data-email-preview-subject]');
    const previewHtml = root.querySelector('[data-email-preview-html]');
    const previewText = root.querySelector('[data-email-preview-text]');
    const htmlCount = root.querySelector('[data-email-html-count]');
    const textCount = root.querySelector('[data-email-text-count]');
    const notice = root.querySelector('[data-workspace-notice]');
    const testRecipient = root.querySelector('[data-email-test-recipient]');
    const testStatus = root.querySelector('[data-email-test-status]');
    const revisionList = root.querySelector('[data-email-revision-list]');
    const toolbar = root.querySelector('.email-builder__quick-toolbar');
    const sourceToggle = root.querySelector('[data-email-source-toggle]');
    const editorShell = root.querySelector('[data-email-editor-shell]');
    const mediaLibraryUrl = root.getAttribute('data-email-media-library-url') || '';
    const templateKey = root.getAttribute('data-email-template-key') || 'unknown';
    const testUrl = root.getAttribute('data-email-test-url') || '';
    const templateLocale = root.getAttribute('data-email-template-locale') || 'en';
    const editable = ['new', 'fallback', 'inactive'].includes(root.getAttribute('data-email-template-status') || '');
    const revisionStorageKey = `barama.email-builder.revisions.${templateKey}.${templateLocale}`;

    let samples = {};
    let defaults = {};
    let lastField = rich || subject || text || html;
    let savedRange = null;
    let activeBlock = null;
    let sourceMode = false;
    let history = [];
    let historyIndex = -1;
    let historyTimer = null;
    let applyingHistory = false;
    let dialogMode = '';
    let selectedMedia = null;
    let mediaSearchTimer = null;

    try { samples = JSON.parse(sampleNode?.textContent || '{}'); } catch (_) { samples = {}; }
    try { defaults = JSON.parse(defaultsNode?.textContent || '{}'); } catch (_) { defaults = {}; }

    const showNotice = (message, type = 'success') => {
        if (!notice) return;
        notice.hidden = false;
        notice.classList.remove('is-success', 'is-error');
        notice.classList.add(type === 'error' ? 'is-error' : 'is-success');
        notice.textContent = message;
        window.setTimeout(() => {
            if (notice.textContent === message) notice.hidden = true;
        }, 7000);
    };

    const normalizeCustomKey = (value) => {
        let suffix = String(value || '').toLowerCase().trim();
        suffix = suffix.replace(/^custom_+/, '');
        suffix = suffix.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/_+/g, '_');
        return `custom_${suffix}`.slice(0, 100);
    };

    let customKeyTouched = false;
    if (createMode && customKeyField) {
        customKeyField.addEventListener('input', () => { customKeyTouched = true; });
        customKeyField.addEventListener('blur', () => {
            customKeyField.value = normalizeCustomKey(customKeyField.value);
        });
        nameField?.addEventListener('input', () => {
            if (customKeyTouched && customKeyField.value !== 'custom_') return;
            const generated = normalizeCustomKey(nameField.value);
            customKeyField.value = generated === 'custom_' ? 'custom_' : generated;
        });
    }

    const replaceVariables = (value) => String(value || '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key) => {
        const normalized = String(key || '').toLowerCase();
        return Object.prototype.hasOwnProperty.call(samples, normalized) ? String(samples[normalized]) : '';
    });

    const syncRichToHtml = () => {
        if (!rich || !html || sourceMode) return;
        html.value = rich.innerHTML.trim();
    };

    const syncHtmlToRich = () => {
        if (!rich || !html) return;
        rich.innerHTML = html.value || '';
    };

    const refresh = () => {
        if (!sourceMode) syncRichToHtml();
        if (previewSubject) previewSubject.textContent = replaceVariables(subject?.value || '');
        if (previewHtml) previewHtml.srcdoc = replaceVariables(html?.value || '');
        if (previewText) previewText.textContent = replaceVariables(text?.value || '');
        if (htmlCount) htmlCount.textContent = `${(html?.value || '').length.toLocaleString()} / 20,000`;
        if (textCount) textCount.textContent = `${(text?.value || '').length.toLocaleString()} / 10,000`;
    };

    const captureState = () => {
        if (!sourceMode) syncRichToHtml();
        return {
            name: nameField?.value || '',
            description: description?.value || '',
            subject: subject?.value || '',
            html: html?.value || '',
            text: text?.value || '',
        };
    };

    const stateSignature = (state) => JSON.stringify(state);

    const applyState = (state) => {
        applyingHistory = true;
        if (nameField) nameField.value = String(state?.name ?? '');
        if (description) description.value = String(state?.description ?? '');
        if (subject) subject.value = String(state?.subject ?? '');
        if (html) html.value = String(state?.html ?? '');
        if (text) text.value = String(state?.text ?? '');
        syncHtmlToRich();
        refresh();
        applyingHistory = false;
    };

    const pushHistory = (state = captureState()) => {
        if (!editable || applyingHistory) return;
        const signature = stateSignature(state);
        if (historyIndex >= 0 && stateSignature(history[historyIndex]) === signature) return;
        history = history.slice(0, historyIndex + 1);
        history.push(state);
        if (history.length > 60) history.shift();
        historyIndex = history.length - 1;
    };

    const scheduleHistory = () => {
        if (!editable || applyingHistory) return;
        window.clearTimeout(historyTimer);
        historyTimer = window.setTimeout(() => pushHistory(), 320);
    };

    const undo = () => {
        window.clearTimeout(historyTimer);
        if (historyIndex <= 0) return;
        historyIndex -= 1;
        applyState(history[historyIndex]);
    };

    const redo = () => {
        window.clearTimeout(historyTimer);
        if (historyIndex < 0 || historyIndex >= history.length - 1) return;
        historyIndex += 1;
        applyState(history[historyIndex]);
    };

    const selectionInsideRich = (range) => {
        if (!rich || !range) return false;
        const container = range.commonAncestorContainer;
        return container === rich || rich.contains(container.nodeType === Node.TEXT_NODE ? container.parentNode : container);
    };

    const saveEditorSelection = () => {
        if (!rich) return;
        const selection = window.getSelection();
        if (!selection || selection.rangeCount < 1) return;
        const range = selection.getRangeAt(0);
        if (!selectionInsideRich(range)) return;
        savedRange = range.cloneRange();
    };

    const restoreEditorSelection = () => {
        if (!rich) return false;
        const selection = window.getSelection();
        if (!selection) return false;
        selection.removeAllRanges();
        if (savedRange && selectionInsideRich(savedRange)) {
            selection.addRange(savedRange);
            return true;
        }
        const range = document.createRange();
        range.selectNodeContents(rich);
        range.collapse(false);
        selection.addRange(range);
        savedRange = range.cloneRange();
        return true;
    };

    document.addEventListener('selectionchange', () => {
        if (document.activeElement === rich || rich?.contains(document.activeElement)) {
            saveEditorSelection();
        }
    });

    const topLevelBlockFromNode = (node) => {
        if (!rich || !node) return null;
        let current = node.nodeType === Node.TEXT_NODE ? node.parentNode : node;
        while (current && current !== rich && current.parentNode !== rich) current = current.parentNode;
        return current && current.parentNode === rich && current.nodeType === Node.ELEMENT_NODE ? current : null;
    };

    rich?.addEventListener('click', (event) => {
        activeBlock = topLevelBlockFromNode(event.target);
        lastField = rich;
        saveEditorSelection();
    });
    rich?.addEventListener('keyup', () => {
        const selection = window.getSelection();
        activeBlock = selection?.anchorNode ? topLevelBlockFromNode(selection.anchorNode) : activeBlock;
        lastField = rich;
        saveEditorSelection();
    });
    rich?.addEventListener('input', () => {
        lastField = rich;
        syncRichToHtml();
        refresh();
        scheduleHistory();
    });
    rich?.addEventListener('focus', () => { lastField = rich; saveEditorSelection(); });

    [nameField, description, subject, text, html].forEach((field) => {
        field?.addEventListener('focus', () => { lastField = field; });
        field?.addEventListener('input', () => {
            if (field === html && !sourceMode) return;
            if (field === html && sourceMode) syncHtmlToRich();
            refresh();
            scheduleHistory();
        });
    });

    root.querySelectorAll('[data-email-builder-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-email-builder-tab');
            root.querySelectorAll('[data-email-builder-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
            root.querySelectorAll('[data-email-builder-panel]').forEach((panel) => {
                panel.hidden = panel.getAttribute('data-email-builder-panel') !== target;
            });
        });
    });

    const insertAtTextField = (field, value) => {
        if (!field || field.readOnly || field.disabled) return;
        const start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
        const end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
        field.setRangeText(value, start, end, 'end');
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const insertHtmlAtSelection = (markup) => {
        if (!editable || !rich || sourceMode) return;
        rich.focus();
        restoreEditorSelection();
        pushHistory();

        let inserted = false;
        try {
            inserted = document.execCommand('insertHTML', false, markup);
        } catch (_) {
            inserted = false;
        }

        if (!inserted) {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount < 1) return;
            const range = selection.getRangeAt(0);
            range.deleteContents();
            const template = document.createElement('template');
            template.innerHTML = markup;
            const fragment = template.content.cloneNode(true);
            const last = fragment.lastChild;
            range.insertNode(fragment);
            if (last) {
                range.setStartAfter(last);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }

        saveEditorSelection();
        syncRichToHtml();
        refresh();
        pushHistory();
    };

    const insertTextAtEditor = (value) => {
        if (!editable || !rich || sourceMode) return;
        rich.focus();
        restoreEditorSelection();
        try {
            document.execCommand('insertText', false, value);
        } catch (_) {
            insertHtmlAtSelection(escapeHtml(value));
            return;
        }
        saveEditorSelection();
        syncRichToHtml();
        refresh();
        scheduleHistory();
    };

    const executeEditorCommand = (command, value = null) => {
        if (!editable || !rich || sourceMode) return;
        rich.focus();
        restoreEditorSelection();
        pushHistory();
        try { document.execCommand(command, false, value); } catch (_) { return; }
        saveEditorSelection();
        syncRichToHtml();
        refresh();
        pushHistory();
    };

    toolbar?.querySelectorAll('button, label').forEach((control) => {
        control.addEventListener('pointerdown', () => saveEditorSelection());
    });

    root.querySelectorAll('[data-email-history]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            button.getAttribute('data-email-history') === 'undo' ? undo() : redo();
        });
    });

    root.querySelectorAll('[data-email-command]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => executeEditorCommand(button.getAttribute('data-email-command') || 'bold'));
    });

    root.querySelectorAll('[data-email-format]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const tag = String(button.getAttribute('data-email-format') || 'p').toUpperCase();
            executeEditorCommand('formatBlock', tag);
        });
    });

    root.querySelectorAll('[data-email-align]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const align = button.getAttribute('data-email-align') || 'left';
            const command = align === 'center' ? 'justifyCenter' : (align === 'right' ? 'justifyRight' : 'justifyLeft');
            executeEditorCommand(command);
        });
    });

    root.querySelector('[data-email-text-color]')?.addEventListener('input', (event) => {
        executeEditorCommand('foreColor', event.currentTarget.value || '#1f2937');
    });
    root.querySelector('[data-email-background-color]')?.addEventListener('input', (event) => {
        const color = event.currentTarget.value || '#fff3bf';
        if (!editable || sourceMode) return;
        rich?.focus();
        restoreEditorSelection();
        pushHistory();
        let applied = false;
        try { applied = document.execCommand('hiliteColor', false, color); } catch (_) { applied = false; }
        if (!applied) {
            try { document.execCommand('backColor', false, color); } catch (_) { /* no-op */ }
        }
        saveEditorSelection();
        syncRichToHtml();
        refresh();
        pushHistory();
    });

    root.querySelector('[data-email-font-size]')?.addEventListener('change', (event) => {
        if (!editable || sourceMode || !rich) return;
        const size = Math.max(10, Math.min(40, Number(event.currentTarget.value || 16)));
        rich.focus();
        restoreEditorSelection();
        pushHistory();
        try { document.execCommand('fontSize', false, '7'); } catch (_) { return; }
        rich.querySelectorAll('font[size="7"]').forEach((font) => {
            const span = document.createElement('span');
            span.style.fontSize = `${size}px`;
            span.style.lineHeight = '1.55';
            while (font.firstChild) span.appendChild(font.firstChild);
            font.replaceWith(span);
        });
        saveEditorSelection();
        syncRichToHtml();
        refresh();
        pushHistory();
    });

    const setSourceMode = (enabled) => {
        sourceMode = Boolean(enabled);
        if (!rich || !html || !editorShell) return;

        if (sourceMode) {
            syncRichToHtml();
            rich.hidden = true;
            html.hidden = false;
            editorShell.classList.add('is-source-mode');
            sourceToggle?.classList.add('is-active');
            sourceToggle?.setAttribute('aria-pressed', 'true');
            html.focus();
            lastField = html;
        } else {
            syncHtmlToRich();
            html.hidden = true;
            rich.hidden = false;
            editorShell.classList.remove('is-source-mode');
            sourceToggle?.classList.remove('is-active');
            sourceToggle?.setAttribute('aria-pressed', 'false');
            rich.focus();
            lastField = rich;
            refresh();
        }

        toolbar?.querySelectorAll('button:not([data-email-source-toggle]), input, select').forEach((control) => {
            control.disabled = sourceMode || !editable;
        });
    };

    sourceToggle?.addEventListener('click', () => setSourceMode(!sourceMode));
    sourceToggle?.setAttribute('aria-pressed', 'false');

    root.querySelectorAll('[data-email-variable]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const value = button.getAttribute('data-email-variable') || '';
            if (lastField === rich && !sourceMode) {
                insertTextAtEditor(value);
            } else if (lastField === html && sourceMode) {
                insertAtTextField(html, value);
            } else {
                insertAtTextField(lastField, value);
            }
        });
    });

    root.querySelectorAll('[data-email-insert-block]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const content = button.getAttribute('data-email-block-html') || '';
            if (sourceMode) {
                insertAtTextField(html, `\n${content}\n`);
            } else {
                insertHtmlAtSelection(content);
            }
        });
    });

    const currentBlock = () => {
        if (activeBlock && activeBlock.parentNode === rich) return activeBlock;
        const selection = window.getSelection();
        if (selection?.anchorNode) return topLevelBlockFromNode(selection.anchorNode);
        return null;
    };

    const blockAction = (action) => {
        if (!editable || sourceMode || !rich) return;
        const block = currentBlock();
        if (!block) {
            showNotice('Place the cursor inside a Text Editor block first.', 'error');
            return;
        }
        pushHistory();
        if (action === 'duplicate') {
            const clone = block.cloneNode(true);
            block.after(clone);
            activeBlock = clone;
        } else if (action === 'delete') {
            const next = block.nextElementSibling || block.previousElementSibling;
            block.remove();
            activeBlock = next || null;
        } else if (action === 'up') {
            const previous = block.previousElementSibling;
            if (!previous) return showNotice('This block is already first.', 'error');
            previous.before(block);
        } else if (action === 'down') {
            const next = block.nextElementSibling;
            if (!next) return showNotice('This block is already last.', 'error');
            next.after(block);
        }
        syncRichToHtml();
        refresh();
        pushHistory();
    };

    root.querySelectorAll('[data-email-block-action]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => blockAction(button.getAttribute('data-email-block-action') || ''));
    });

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const escapeAttr = escapeHtml;

    const allowedEmailUrl = (value) => {
        const url = String(value || '').trim();
        if (/^https?:\/\//i.test(url)) return url;
        if (/^\{\{\s*app_url\s*\}\}(?:\/|$)/i.test(url)) return url;
        return '';
    };

    const photoMarkup = (url, alt, clickUrl = '') => {
        const src = allowedEmailUrl(url);
        if (!src) throw new Error('Enter an absolute http(s) image URL or use {{app_url}}.');
        const click = clickUrl ? allowedEmailUrl(clickUrl) : '';
        if (clickUrl && !click) throw new Error('The optional click URL must use http(s) or {{app_url}}.');
        const image = `<img src="${escapeAttr(src)}" alt="${escapeAttr(alt || '')}" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;outline:none;text-decoration:none;margin:0 auto;">`;
        const content = click ? `<a href="${escapeAttr(click)}" style="text-decoration:none;border:0;">${image}</a>` : image;
        return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:18px 0;"><tr><td align="center" style="padding:0;">${content}</td></tr></table>`;
    };

    const videoMarkup = (url, label, poster = '') => {
        const href = allowedEmailUrl(url);
        if (!href) throw new Error('Enter an absolute http(s) video URL.');
        const safeLabel = escapeHtml(label || 'Watch video');
        const posterUrl = poster ? allowedEmailUrl(poster) : '';
        if (poster && !posterUrl) throw new Error('Poster image URL must use http(s) or {{app_url}}.');
        const visual = posterUrl
            ? `<a href="${escapeAttr(href)}" style="text-decoration:none;border:0;"><img src="${escapeAttr(posterUrl)}" alt="${escapeAttr(label || 'Watch video')}" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;outline:none;margin:0 auto;"></a>`
            : `<a href="${escapeAttr(href)}" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;">▶ ${safeLabel}</a>`;
        const caption = posterUrl ? `<div style="padding-top:10px;"><a href="${escapeAttr(href)}" style="color:#2563eb;text-decoration:underline;font-weight:700;">▶ ${safeLabel}</a></div>` : '';
        return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:18px 0;"><tr><td align="center" style="padding:0;">${visual}${caption}</td></tr></table>`;
    };

    const youtubeId = (value) => {
        const url = String(value || '').trim();
        const patterns = [
            /youtu\.be\/([A-Za-z0-9_-]{6,})/i,
            /youtube\.com\/watch\?(?:.*&)?v=([A-Za-z0-9_-]{6,})/i,
            /youtube\.com\/(?:embed|shorts)\/([A-Za-z0-9_-]{6,})/i,
        ];
        for (const pattern of patterns) {
            const match = url.match(pattern);
            if (match?.[1]) return match[1];
        }
        return '';
    };

    const youtubeMarkup = (url, label) => {
        const id = youtubeId(url);
        if (!id) throw new Error('Enter a valid YouTube video URL.');
        const href = `https://www.youtube.com/watch?v=${encodeURIComponent(id)}`;
        const poster = `https://i.ytimg.com/vi/${encodeURIComponent(id)}/hqdefault.jpg`;
        return videoMarkup(href, label || 'Watch on YouTube', poster);
    };

    const linkMarkup = (url, label) => {
        const href = allowedEmailUrl(url);
        if (!href) throw new Error('Enter an absolute http(s) URL or use {{app_url}}.');
        return `<a href="${escapeAttr(href)}" style="color:#2563eb;text-decoration:underline;">${escapeHtml(label || href)}</a>`;
    };

    const dialog = root.querySelector('[data-email-insert-dialog]');
    const dialogTitle = root.querySelector('[data-email-dialog-title]');
    const dialogHelp = root.querySelector('[data-email-dialog-help]');
    const dialogForm = root.querySelector('[data-email-dialog-form]');
    const dialogInsert = root.querySelector('[data-email-dialog-insert]');
    const dialogUrl = root.querySelector('[data-email-dialog-url]');
    const dialogUrlLabel = root.querySelector('[data-email-dialog-url-label]');
    const dialogLabel = root.querySelector('[data-email-dialog-label]');
    const dialogAlt = root.querySelector('[data-email-dialog-alt]');
    const dialogPoster = root.querySelector('[data-email-dialog-poster]');
    const dialogClick = root.querySelector('[data-email-dialog-click]');
    const mediaLibrary = root.querySelector('[data-email-media-library]');
    const mediaSearch = root.querySelector('[data-email-media-search]');
    const mediaType = root.querySelector('[data-email-media-type]');
    const mediaGrid = root.querySelector('[data-email-media-grid]');
    const mediaStatus = root.querySelector('[data-email-media-status]');

    const setDialogField = (name, visible) => {
        const field = root.querySelector(`[data-email-dialog-field="${name}"]`);
        if (field) field.hidden = !visible;
    };

    const resetDialogFields = () => {
        [dialogUrl, dialogLabel, dialogAlt, dialogPoster, dialogClick].forEach((field) => { if (field) field.value = ''; });
        selectedMedia = null;
        mediaGrid?.querySelectorAll('.is-selected').forEach((item) => item.classList.remove('is-selected'));
    };

    const selectedEditorText = () => {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount < 1) return '';
        const range = selection.getRangeAt(0);
        return selectionInsideRich(range) ? selection.toString().trim() : '';
    };

    const openDialog = (mode) => {
        if (!dialog || !editable) return;
        saveEditorSelection();
        dialogMode = mode;
        resetDialogFields();
        dialog.hidden = false;
        dialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('email-builder-dialog-open');
        if (dialogForm) dialogForm.hidden = mode === 'media';
        if (mediaLibrary) mediaLibrary.hidden = mode !== 'media';
        if (dialogInsert) dialogInsert.disabled = mode === 'media';

        const config = {
            photo: ['Insert photo', 'Use an absolute image URL. The editor generates responsive email-safe image markup.', 'Image URL'],
            video: ['Insert video', 'Email clients do not reliably play embedded video. Barama inserts a linked poster/card instead.', 'Video URL'],
            youtube: ['Insert YouTube', 'YouTube is inserted as a clickable thumbnail linked to the video, not an iframe.', 'YouTube URL'],
            link: ['Insert link', 'Add a standard email-safe link at the current cursor position.', 'Link URL'],
            media: ['Media Library', 'Choose an existing Barama image or video. Video assets are inserted as email-safe links.', 'URL'],
        }[mode] || ['Insert content', 'Add email-safe content.', 'URL'];

        if (dialogTitle) dialogTitle.textContent = config[0];
        if (dialogHelp) dialogHelp.textContent = config[1];
        if (dialogUrlLabel) dialogUrlLabel.textContent = config[2];

        setDialogField('url', ['photo', 'video', 'youtube', 'link'].includes(mode));
        setDialogField('label', ['video', 'youtube', 'link'].includes(mode));
        setDialogField('alt', mode === 'photo');
        setDialogField('poster', mode === 'video');
        setDialogField('click', mode === 'photo');

        if (mode === 'link' && dialogLabel) dialogLabel.value = selectedEditorText();
        if (mode === 'media') {
            if (mediaSearch) mediaSearch.value = '';
            if (mediaType) mediaType.value = 'all';
            loadMediaLibrary();
            mediaSearch?.focus();
        } else {
            window.setTimeout(() => dialogUrl?.focus(), 30);
        }
    };

    const closeDialog = () => {
        if (!dialog) return;
        dialog.hidden = true;
        dialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('email-builder-dialog-open');
        dialogMode = '';
        selectedMedia = null;
        if (rich && !sourceMode) {
            rich.focus();
            restoreEditorSelection();
        }
    };

    root.querySelectorAll('[data-email-dialog-close]').forEach((button) => button.addEventListener('click', closeDialog));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog && !dialog.hidden) closeDialog();
    });

    const renderMediaLibrary = (items) => {
        if (!mediaGrid || !mediaStatus) return;
        mediaGrid.replaceChildren();
        selectedMedia = null;
        if (dialogInsert) dialogInsert.disabled = true;
        if (!Array.isArray(items) || items.length === 0) {
            mediaStatus.hidden = false;
            mediaStatus.textContent = 'No image or video assets match this search.';
            return;
        }
        mediaStatus.hidden = true;
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'email-builder__media-item';
            const preview = document.createElement('span');
            preview.className = 'email-builder__media-item-preview';
            if (item.media_type === 'image') {
                const image = document.createElement('img');
                image.src = String(item.url || '');
                image.alt = String(item.alt_text || item.name || '');
                image.loading = 'lazy';
                preview.appendChild(image);
            } else {
                preview.classList.add('is-video');
                preview.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/></svg>';
            }
            const meta = document.createElement('span');
            meta.className = 'email-builder__media-item-meta';
            const name = document.createElement('strong');
            name.textContent = String(item.name || 'Media asset');
            const type = document.createElement('small');
            type.textContent = String(item.mime_type || item.media_type || 'media');
            meta.append(name, type);
            button.append(preview, meta);
            button.addEventListener('click', () => {
                mediaGrid.querySelectorAll('.is-selected').forEach((entry) => entry.classList.remove('is-selected'));
                button.classList.add('is-selected');
                selectedMedia = item;
                if (dialogInsert) dialogInsert.disabled = false;
            });
            mediaGrid.appendChild(button);
        });
    };

    async function loadMediaLibrary() {
        if (!mediaLibraryUrl || !mediaStatus) {
            if (mediaStatus) mediaStatus.textContent = 'Media Library endpoint is unavailable.';
            return;
        }
        mediaStatus.hidden = false;
        mediaStatus.textContent = 'Loading media…';
        if (mediaGrid) mediaGrid.replaceChildren();
        const query = String(mediaSearch?.value || '').trim();
        const type = String(mediaType?.value || 'all');
        const url = `${mediaLibraryUrl}?q=${encodeURIComponent(query)}&type=${encodeURIComponent(type)}`;
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || payload?.ok !== true) {
                throw new Error(payload?.message || `Media Library failed with HTTP ${response.status}.`);
            }
            renderMediaLibrary(payload?.data?.items || []);
        } catch (error) {
            mediaStatus.hidden = false;
            mediaStatus.textContent = error instanceof Error ? error.message : 'Media Library could not be loaded.';
        }
    }

    mediaSearch?.addEventListener('input', () => {
        window.clearTimeout(mediaSearchTimer);
        mediaSearchTimer = window.setTimeout(loadMediaLibrary, 260);
    });
    mediaType?.addEventListener('change', loadMediaLibrary);

    root.querySelectorAll('[data-email-insert-tool]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const mode = button.getAttribute('data-email-insert-tool') || '';
            if (mode === 'text') {
                insertHtmlAtSelection('<p style="margin:0 0 16px;line-height:1.65;">Write your text here.</p>');
                return;
            }
            openDialog(mode);
        });
    });

    dialogInsert?.addEventListener('click', () => {
        try {
            let markup = '';
            if (dialogMode === 'photo') {
                markup = photoMarkup(dialogUrl?.value || '', dialogAlt?.value || '', dialogClick?.value || '');
            } else if (dialogMode === 'video') {
                markup = videoMarkup(dialogUrl?.value || '', dialogLabel?.value || 'Watch video', dialogPoster?.value || '');
            } else if (dialogMode === 'youtube') {
                markup = youtubeMarkup(dialogUrl?.value || '', dialogLabel?.value || 'Watch on YouTube');
            } else if (dialogMode === 'link') {
                markup = linkMarkup(dialogUrl?.value || '', dialogLabel?.value || 'Open link');
            } else if (dialogMode === 'media') {
                if (!selectedMedia) throw new Error('Choose an item from the Media Library first.');
                markup = selectedMedia.media_type === 'video'
                    ? videoMarkup(String(selectedMedia.url || ''), String(selectedMedia.name || 'Watch video'), '')
                    : photoMarkup(String(selectedMedia.url || ''), String(selectedMedia.alt_text || selectedMedia.name || ''), '');
            }
            if (!markup) throw new Error('Nothing was selected to insert.');
            closeDialog();
            insertHtmlAtSelection(markup);
        } catch (error) {
            showNotice(error instanceof Error ? error.message : 'Content could not be inserted.', 'error');
        }
    });

    root.querySelectorAll('[data-email-preview-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.getAttribute('data-email-preview-mode');
            root.querySelectorAll('[data-email-preview-mode]').forEach((item) => item.classList.toggle('is-active', item === button));
            root.querySelector('[data-email-preview-frame]')?.classList.toggle('is-mobile', mode === 'mobile');
        });
    });

    const loadRevisions = () => {
        try {
            const parsed = JSON.parse(localStorage.getItem(revisionStorageKey) || '[]');
            return Array.isArray(parsed) ? parsed.slice(0, 12) : [];
        } catch (_) {
            return [];
        }
    };

    const saveRevisions = (revisions) => {
        try { localStorage.setItem(revisionStorageKey, JSON.stringify(revisions.slice(0, 12))); } catch (_) { /* storage may be disabled */ }
    };

    const renderRevisions = () => {
        if (!revisionList) return;
        const revisions = loadRevisions();
        revisionList.replaceChildren();
        if (!revisions.length) {
            const empty = document.createElement('p');
            empty.textContent = 'No browser snapshots yet.';
            revisionList.append(empty);
            return;
        }
        revisions.forEach((revision, index) => {
            const row = document.createElement('div');
            row.className = 'email-builder__revision-row';
            const meta = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = revision.label || `Snapshot ${index + 1}`;
            const time = document.createElement('small');
            time.textContent = revision.created_at ? new Date(revision.created_at).toLocaleString() : 'Saved locally';
            meta.append(title, time);
            const restore = document.createElement('button');
            restore.type = 'button';
            restore.textContent = 'Restore';
            restore.disabled = !editable;
            restore.addEventListener('click', () => {
                if (!editable || !revision.state) return;
                pushHistory();
                applyState(revision.state);
                pushHistory();
                showNotice('Browser snapshot restored into the editor. Save the template when ready.');
            });
            row.append(meta, restore);
            revisionList.append(row);
        });
    };

    const saveSnapshot = (label = 'Manual snapshot') => {
        if (!editable) return;
        const revisions = loadRevisions();
        revisions.unshift({ label, created_at: new Date().toISOString(), state: captureState() });
        saveRevisions(revisions);
        renderRevisions();
    };

    root.querySelector('[data-email-revision-save]')?.addEventListener('click', () => {
        saveSnapshot('Manual snapshot');
        showNotice('Browser revision snapshot saved locally.');
    });

    root.querySelector('[data-email-revision-clear]')?.addEventListener('click', () => {
        if (!editable) return;
        saveRevisions([]);
        renderRevisions();
        showNotice('Browser revision snapshots cleared.');
    });

    root.querySelector('[data-email-reset-defaults]')?.addEventListener('click', () => {
        if (!editable) return;
        saveSnapshot('Before reset to default');
        pushHistory();
        applyState({
            name: String(defaults.name || nameField?.value || ''),
            description: String(defaults.description || ''),
            subject: String(defaults.subject || ''),
            html: String(defaults.html || ''),
            text: String(defaults.text || ''),
        });
        pushHistory();
        showNotice('Barama default content restored into the editor. Nothing is live until you save and activate it.');
    });

    const appendNamedFields = (payload) => {
        syncRichToHtml();
        form?.querySelectorAll('[name]').forEach((field) => {
            if (!field.name || field.disabled) return;
            if (field.type === 'checkbox' && !field.checked) return;
            payload.set(field.name, field.value);
        });
        payload.set('template_key', templateKey);
        payload.set('locale', templateLocale);
        root.querySelectorAll('[data-email-test-csrf] input[name]').forEach((field) => payload.set(field.name, field.value));
        if (!form || !form.querySelector('[name="subject_template"]')) {
            if (nameField) payload.set('name', nameField.value);
            if (description) payload.set('description', description.value);
            if (subject) payload.set('subject_template', subject.value);
            if (html) payload.set('html_template', html.value);
            if (text) payload.set('text_template', text.value);
        }
    };

    createForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!createMode || !customKeyField) return;
        syncRichToHtml();
        const submitter = event.submitter;
        const key = normalizeCustomKey(customKeyField.value);
        customKeyField.value = key;

        if (!/^custom_[a-z0-9][a-z0-9_]{2,92}$/.test(key)) {
            showNotice('Enter a custom template key with at least 3 characters after custom_. Use lowercase letters, numbers, and underscores only.', 'error');
            customKeyField.focus();
            return;
        }
        if (!String(nameField?.value || '').trim()) {
            showNotice('Enter a template name before creating the custom template.', 'error');
            nameField?.focus();
            return;
        }
        if (!String(html?.value || '').trim()) {
            showNotice('Add email content in the Text Editor before creating the template.', 'error');
            rich?.focus();
            return;
        }

        if (submitter) submitter.disabled = true;
        try {
            const response = await fetch(createForm.action, {
                method: createForm.method || 'POST',
                body: new FormData(createForm),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.ok) {
                throw new Error(result?.message || `Template creation failed with HTTP ${response.status}.`);
            }
            showNotice(result?.data?.message || 'Custom email template created as inactive. Opening the Builder…');
            window.setTimeout(() => window.location.assign(`/dashboard/email-templates/${encodeURIComponent(key)}/builder`), 320);
        } catch (error) {
            showNotice(error instanceof Error ? error.message : 'Custom email template could not be created.', 'error');
            if (submitter) submitter.disabled = false;
        }
    });

    if (form instanceof HTMLFormElement && form !== createForm) {
        form.addEventListener('submit', () => syncRichToHtml(), true);
    }

    root.querySelector('[data-email-send-test]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const recipient = String(testRecipient?.value || '').trim();
        if (!recipient || !testUrl) {
            showNotice('Enter a recipient email address before sending the test.', 'error');
            testRecipient?.focus();
            return;
        }
        const payload = new FormData();
        appendNamedFields(payload);
        payload.set('test_recipient', recipient);
        button.disabled = true;
        if (testStatus) testStatus.textContent = 'Sending test email…';
        try {
            const response = await fetch(testUrl, {
                method: 'POST',
                body: payload,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.ok) {
                throw new Error(result?.message || `Test delivery failed with HTTP ${response.status}.`);
            }
            const message = result?.data?.message || `Test email sent to ${recipient}.`;
            if (testStatus) testStatus.textContent = message;
            showNotice(message);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Test email could not be sent.';
            if (testStatus) testStatus.textContent = message;
            showNotice(message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    syncHtmlToRich();
    history = [captureState()];
    historyIndex = 0;
    renderRevisions();
    refresh();
})();

};

/* ===== finance-payment-providers ===== */
modules["finance-payment-providers"]=function(__baramaScript){

/* BARAMA_PAYMENT_PROVIDERS_PREMIUM_V100 */
(() => {
    'use strict';

    const openDialog = (id) => {
        const dialog = document.getElementById(id);
        if (!(dialog instanceof HTMLDialogElement)) return;
        if (!dialog.open) dialog.showModal();
        window.setTimeout(() => {
            dialog.querySelector('input:not([type="hidden"]):not([readonly]), select, button')?.focus();
        }, 20);
    };

    document.querySelectorAll('[data-provider-dialog-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-provider-dialog-open');
            if (id) openDialog(id);
        });
    });

    document.querySelectorAll('[data-provider-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = button.closest('dialog');
            if (dialog instanceof HTMLDialogElement) dialog.close();
        });
    });

    document.querySelectorAll('.payment-provider-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    document.querySelectorAll('[data-provider-toggle]').forEach((toggle) => {
        toggle.addEventListener('change', () => {
            if (!(toggle instanceof HTMLInputElement) || toggle.disabled) return;
            const form = toggle.closest('form');
            const status = form?.querySelector('input[name="status"]');
            if (!(form instanceof HTMLFormElement) || !(status instanceof HTMLInputElement)) return;
            status.value = toggle.checked ? 'active' : 'inactive';
            form.requestSubmit();
        });
    });

    document.querySelectorAll('[data-provider-token-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-provider-token-toggle');
            const field = id ? document.getElementById(id) : null;
            if (!(field instanceof HTMLInputElement)) return;
            const reveal = field.type === 'password';
            field.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        });
    });
})();

};

/* ===== finance-payment-methods ===== */
modules["finance-payment-methods"]=function(__baramaScript){
/* Barama v1.0.0 canonical JavaScript; original independent IIFEs preserved. */

/* -- finance-payment-methods-v228.js -- */
/* BARAMA_PAYMENT_METHODS_NATIVE_POST_V128 */
/* BARAMA_PAYMENT_METHODS_V110 */
(()=>{'use strict';document.querySelectorAll('[data-payment-method-dialog-open]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-method-dialog-open');const dialog=id?document.getElementById(id):null;if(dialog instanceof HTMLDialogElement&&!dialog.open)dialog.showModal();});});document.querySelectorAll('[data-payment-method-dialog-close]').forEach(button=>{button.addEventListener('click',()=>{const dialog=button.closest('dialog');if(dialog instanceof HTMLDialogElement)dialog.close();});});document.querySelectorAll('.payment-method-dialog').forEach(dialog=>{dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});});document.querySelectorAll('[data-payment-method-toggle]').forEach(toggle=>{toggle.addEventListener('change',()=>{if(!(toggle instanceof HTMLInputElement)||toggle.disabled)return;const form=toggle.closest('form');const status=form?.querySelector('input[name="status"]');if(!(form instanceof HTMLFormElement)||!(status instanceof HTMLInputElement))return;status.value=toggle.checked?'active':'inactive';form.requestSubmit();});});document.querySelectorAll('[data-payment-secret-toggle]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-secret-toggle');const field=id?document.getElementById(id):null;if(!(field instanceof HTMLInputElement))return;const reveal=field.type==='password';field.type=reveal?'text':'password';button.textContent=reveal?'Hide':'Show';button.setAttribute('aria-pressed',reveal?'true':'false');});});})();


/* -- finance-payment-methods-v230.js -- */
(() => {
    'use strict';
    // BARAMA_PAYMENT_METHODS_V120
    const normalize = (value) => String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40);

    document.addEventListener('input', (event) => {
        const input = event.target.closest('[data-payment-code-source]');
        if (!input) return;
        const target = document.querySelector(input.dataset.paymentCodeSource || '');
        if (target) target.value = normalize(input.value);
    });

    document.addEventListener('blur', (event) => {
        const input = event.target.closest('[data-payment-normalize-code]');
        if (input) input.value = normalize(input.value);
    }, true);

    document.querySelectorAll('[data-payment-add-kind]').forEach((button) => {
        button.addEventListener('click', () => {
            const kind = button.dataset.paymentAddKind;
            document.querySelectorAll('[data-payment-add-kind]').forEach((item) => {
                item.classList.toggle('is-active', item === button);
                item.setAttribute('aria-selected', item === button ? 'true' : 'false');
            });
            document.querySelectorAll('[data-payment-add-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.paymentAddPanel !== kind;
            });
        });
    });

    document.addEventListener('change', (event) => {
        const input = event.target.closest('[data-payment-qr-input]');
        if (!input) return;
        const target = document.querySelector(input.dataset.paymentQrInput || '');
        const file = input.files && input.files[0];
        if (!target || !file) return;
        const url = URL.createObjectURL(file);
        target.src = url;
        target.hidden = false;
        target.onload = () => URL.revokeObjectURL(url);
    });

    let dialog = document.getElementById('payment-terms-preview-dialog');
    if (!dialog) {
        dialog = document.createElement('dialog');
        dialog.id = 'payment-terms-preview-dialog';
        dialog.className = 'payment-terms-preview-dialog';
        dialog.innerHTML = '<div class="payment-terms-preview-dialog__shell"><div class="payment-terms-preview-dialog__head"><strong data-payment-terms-preview-title>Payment transfer terms</strong><button type="button" class="btn btn-outline-secondary" data-payment-terms-preview-close>Close</button></div><div class="payment-terms-preview-dialog__body" data-payment-terms-preview-body></div></div>';
        document.body.appendChild(dialog);
    }

    document.addEventListener('click', (event) => {
        const preview = event.target.closest('[data-payment-terms-preview]');
        if (preview) {
            const form = preview.closest('form');
            if (!form) return;
            dialog.querySelector('[data-payment-terms-preview-title]').textContent = form.querySelector('[name="terms_title"]')?.value?.trim() || 'Payment transfer terms and conditions';
            dialog.querySelector('[data-payment-terms-preview-body]').textContent = form.querySelector('[name="terms_content"]')?.value?.trim() || 'No terms have been entered yet.';
            dialog.showModal();
            return;
        }
        if (event.target.closest('[data-payment-terms-preview-close]')) dialog.close();
    });
})();


};

/* ===== finance-plans ===== */
modules["finance-plans"]=function(__baramaScript){
/* BARAMA_FINANCE_PLANS_PREMIUM_V139 */
(() => {
    'use strict';

    const workspace = document.querySelector('[data-plans-workspace]');
    const modal = document.querySelector('[data-plan-modal]');
    const form = document.querySelector('[data-plan-form]');
    const presetNode = document.getElementById('finance-plan-presets');

    if (!workspace || !modal || !form || !presetNode) return;

    let presets = {};

    try {
        presets = JSON.parse(presetNode.textContent || '{}');
    } catch (_) {
        presets = {};
    }

    const title = modal.querySelector('[data-plan-title]');
    const subtitle = modal.querySelector('[data-plan-subtitle]');
    const submitLabel = modal.querySelector('[data-plan-submit-label]');
    const notice = modal.querySelector('[data-plan-notice]');
    let lastTrigger = null;
    let slugTouched = false;

    const field = (name) => form.elements.namedItem(name);

    const setValue = (name, value) => {
        const input = field(name);
        if (!input) return;
        input.value = value ?? '';
    };

    const slugify = (value) => String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const resetCreate = () => {
        form.reset();
        form.action = `${window.location.origin}/dashboard/plans`;
        title.textContent = 'Create Plan';
        subtitle.textContent = 'Configure pricing and commercial entitlements.';
        submitLabel.textContent = 'Create inactive plan';
        notice.textContent = 'New plans are created inactive. Review them before activation.';
        slugTouched = false;
    };

    const applyPreset = (key) => {
        if (key === 'custom') {
            resetCreate();
            return;
        }

        const preset = presets[key];
        if (!preset) return;

        resetCreate();

        Object.entries(preset).forEach(([name, value]) => {
            setValue(name, value);
        });

        slugTouched = true;
        subtitle.textContent = `${preset.name} preset · every field remains editable`;
    };

    const applyPlan = (payload, duplicate = false) => {
        resetCreate();

        Object.entries(payload).forEach(([name, value]) => {
            if (field(name)) {
                setValue(name, value);
            }
        });

        if (duplicate) {
            const originalName = payload.name || 'Plan';
            setValue('name', `${originalName} Copy`);
            setValue('slug', `${slugify(originalName)}-copy`);
            title.textContent = 'Duplicate Plan';
            subtitle.textContent = 'Create a new inactive plan from the selected configuration.';
            submitLabel.textContent = 'Create duplicate';
            slugTouched = true;
            return;
        }

        form.action = `${window.location.origin}/dashboard/plans/${payload.id}`;
        title.textContent = 'Edit Plan';
        subtitle.textContent = `Update ${payload.name || 'plan'} pricing and entitlements.`;
        submitLabel.textContent = 'Save plan changes';
        notice.textContent = 'Editing preserves the current activation status and subscriptions.';
        slugTouched = true;
    };

    const show = (trigger) => {
        lastTrigger = trigger;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('finance-plan-modal-open');

        modal
            .querySelector('input[name="name"]')
            ?.focus({preventScroll: true});
    };

    const hide = () => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('finance-plan-modal-open');
        lastTrigger?.focus({preventScroll: true});
        lastTrigger = null;
    };

    document.addEventListener('click', (event) => {
        const open = event.target.closest('[data-plan-open]');
        if (open) {
            resetCreate();
            show(open);
            return;
        }

        const preset = event.target.closest('[data-plan-preset]');
        if (preset && !preset.disabled) {
            const key = preset.getAttribute('data-plan-preset') || 'custom';
            applyPreset(key);
            show(preset);
            return;
        }

        const edit = event.target.closest('[data-plan-edit]');
        if (edit) {
            try {
                const payload = JSON.parse(
                    edit.getAttribute('data-plan-edit') || '{}'
                );
                applyPlan(payload, false);
                show(edit);
            } catch (_) {
                return;
            }
            return;
        }

        const duplicate = event.target.closest('[data-plan-duplicate]');
        if (duplicate) {
            try {
                const payload = JSON.parse(
                    duplicate.getAttribute('data-plan-duplicate') || '{}'
                );
                applyPlan(payload, true);
                show(duplicate);
            } catch (_) {
                return;
            }
            return;
        }

        if (event.target.closest('[data-plan-close]')) {
            hide();
        }
    });

    field('slug')?.addEventListener('input', () => {
        slugTouched = true;
    });

    field('name')?.addEventListener('input', (event) => {
        if (!slugTouched) {
            setValue('slug', slugify(event.target.value));
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            hide();
        }
    });
})();

};

/* ===== finance-plans-actions ===== */
modules["finance-plans-actions"]=function(__baramaScript){
/* Barama Plans row actions — viewport-safe positioning v1.8.1.
   Native details/summary remains keyboard and no-JS operable. */
(() => {
  'use strict';

  const page = document.querySelector('[data-plans-workspace]');
  if (!page) return;
  const details = Array.from(page.querySelectorAll('.finance-plans__table details.workspace-row-actions'));
  if (!details.length) return;

  const margin = 12;
  const closeAll = (except = null) => {
    details.forEach(entry => {
      if (entry !== except && entry.open) entry.open = false;
    });
  };

  const position = entry => {
    if (!entry.open || !entry.isConnected) return;
    const trigger = entry.querySelector('summary');
    const menu = entry.querySelector('.workspace-row-actions__menu');
    if (!trigger || !menu) return;

    const bounds = page.getBoundingClientRect();
    const rect = trigger.getBoundingClientRect();
    const width = Math.min(menu.offsetWidth || 174, Math.max(0, window.innerWidth - margin * 2));
    const height = menu.offsetHeight || 0;

    // Keep the dropdown inside the actual Plans page, not clipped off the
    // right of the screen or outside the main workspace content.
    const leftBoundary = Math.max(margin, bounds.left + margin);
    const rightBoundary = Math.min(window.innerWidth - margin, bounds.right - margin);
    const maxLeft = Math.max(leftBoundary, rightBoundary - width);
    const left = Math.min(maxLeft, Math.max(leftBoundary, rect.right - width));

    // Prefer beneath the trigger; flip above the row when near the bottom.
    const below = window.innerHeight - rect.bottom - margin;
    const above = rect.top - margin;
    const openAbove = below < height + 6 && above > below;
    const top = openAbove
      ? Math.max(margin, rect.top - height - 6)
      : Math.min(Math.max(margin, rect.bottom + 6), Math.max(margin, window.innerHeight - height - margin));

    menu.style.left = Math.round(left) + 'px';
    menu.style.top = Math.round(top) + 'px';
    menu.style.right = 'auto';
    menu.style.bottom = 'auto';
    menu.style.maxHeight = Math.max(70, window.innerHeight - margin * 2) + 'px';
    menu.style.overflowY = 'auto';
  };

  details.forEach(entry => {
    entry.addEventListener('toggle', () => {
      if (!entry.open) return;
      closeAll(entry);
      window.requestAnimationFrame(() => position(entry));
    });
  });

  document.addEventListener('pointerdown', event => {
    if (!details.some(entry => entry.contains(event.target))) closeAll();
  });

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const active = details.find(entry => entry.open);
    if (!active) return;
    active.open = false;
    active.querySelector('summary')?.focus();
    event.preventDefault();
  });

  // The existing plan editor handles these buttons with event delegation.
  // Close the popover after a successful click without changing its action.
  document.addEventListener('click', event => {
    if (event.target.closest('[data-plan-edit], [data-plan-duplicate]')) closeAll();
  });

  // A floating menu must not drift from its row on any ancestor scroll.
  document.addEventListener('scroll', () => closeAll(), true);
  window.addEventListener('resize', () => closeAll());
})();

};

/* ===== admin-footer-links ===== */
modules["admin-footer-links"]=function(__baramaScript){
/* Footer editor menu rows: accessible add/remove/reorder, no extra dependencies. */
(() => {
  'use strict';

  function initialize() {
    const form = document.querySelector('.barama-footer-editor__form');
    if (!form) return;

    const MAX_ITEMS = 15;
    const columns = ['brand', 'marketplace', 'compliance', 'help'];

    function listFor(column) {
      if (!columns.includes(column)) return null;
      return form.querySelector('[data-footer-list="' + column + '"]');
    }

    function rows(list) {
      return Array.from(list.querySelectorAll(':scope > [data-footer-item]'));
    }

    function sync(column) {
      const list = listFor(column);
      if (!list) return;
      const items = rows(list);
      items.forEach((item, index) => {
        const label = item.querySelector('[data-footer-label]');
        const url = item.querySelector('[data-footer-url]');
        if (label) label.name = 'footer_links[' + column + '][' + index + '][label]';
        if (url) url.name = 'footer_links[' + column + '][' + index + '][url]';

        const up = item.querySelector('[data-footer-up]');
        const down = item.querySelector('[data-footer-down]');
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === items.length - 1;
      });

      const host = list.closest('[data-footer-column]');
      const add = host?.querySelector('[data-footer-add]');
      const empty = host?.querySelector('[data-footer-empty]');
      if (add) {
        add.disabled = items.length >= MAX_ITEMS;
        add.title = add.disabled ? 'Maximum 15 menu items per column' : 'Add a menu item';
      }
      if (empty) empty.hidden = items.length !== 0;
    }

    function createRow() {
      const item = document.createElement('div');
      item.className = 'barama-footer-editor__link-row';
      item.dataset.footerItem = '';

      const fields = document.createElement('div');
      fields.className = 'barama-footer-editor__link-fields';

      for (const [labelText, flag, max, placeholder] of [
        ['Label', 'footerLabel', 80, 'Menu label'],
        ['URL', 'footerUrl', 500, '/page or https://example.com'],
      ]) {
        const wrapper = document.createElement('label');
        wrapper.textContent = labelText;
        const input = document.createElement('input');
        input.type = 'text';
        input.maxLength = max;
        input.required = true;
        input.placeholder = placeholder;
        input.dataset[flag] = '';
        wrapper.append(input);
        fields.append(wrapper);
      }

      const actions = document.createElement('div');
      actions.className = 'barama-footer-editor__item-actions';
      actions.setAttribute('role', 'group');
      actions.setAttribute('aria-label', 'Reorder or remove menu item');

      for (const [flag, text, description] of [
        ['footerUp', '↑ Up', 'Move item up'],
        ['footerDown', '↓ Down', 'Move item down'],
        ['footerRemove', 'Remove', 'Remove menu item'],
      ]) {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset[flag] = '';
        button.textContent = text;
        button.setAttribute('aria-label', description);
        actions.append(button);
      }
      item.append(fields, actions);
      return item;
    }

    form.addEventListener('click', (event) => {
      const target = event.target.closest('button');
      if (!target || !form.contains(target)) return;

      if (target.hasAttribute('data-footer-add')) {
        const column = target.dataset.footerAdd;
        const list = listFor(column);
        if (!list || rows(list).length >= MAX_ITEMS) return;
        const row = createRow();
        list.append(row);
        sync(column);
        row.querySelector('[data-footer-label]')?.focus();
        return;
      }

      const row = target.closest('[data-footer-item]');
      const list = row?.parentElement;
      const column = list?.dataset.footerList;
      if (!row || !listFor(column) || !list.isSameNode(listFor(column))) return;

      if (target.hasAttribute('data-footer-remove')) {
        const focus = row.nextElementSibling || row.previousElementSibling;
        row.remove();
        sync(column);
        focus?.querySelector('[data-footer-label]')?.focus();
        if (!focus) {
          form.querySelector('[data-footer-add="' + column + '"]')?.focus();
        }
      } else if (target.hasAttribute('data-footer-up')) {
        const prev = row.previousElementSibling;
        if (prev) list.insertBefore(row, prev);
        sync(column);
        row.querySelector('[data-footer-up]')?.focus();
      } else if (target.hasAttribute('data-footer-down')) {
        const next = row.nextElementSibling;
        if (next) list.insertBefore(next, row);
        sync(column);
        row.querySelector('[data-footer-down]')?.focus();
      }
    });

    form.addEventListener('submit', () => {
      columns.forEach(sync);
    });
    columns.forEach(sync);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();

};

/* ===== catalog-quick-actions ===== */
modules["catalog-quick-actions"]=function(__baramaScript){
/* Barama Catalog v1.0.17-A8 — single-row header actions and on-demand REAL forms.
   No new endpoints; no location or Mapbox data is modified here. */
(() => {
  'use strict';

  const buttons = Array.from(document.querySelectorAll('[data-barama-catalog-open]'));
  if (buttons.length !== 2) return;

  const entries = buttons.map(button => ({
    button,
    panel: document.getElementById(button.getAttribute('aria-controls') || '')
  }));

  if (entries.some(({ panel }) => !panel || panel.tagName !== 'DETAILS')) return;
  const editors = entries[0].panel.closest('.workspace-form-pair');
  if (!editors || entries.some(({ panel }) => panel.parentElement !== editors)) return;

  function update() {
    let expanded = false;
    entries.forEach(({ button, panel }) => {
      const isOpen = panel.open;
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      expanded = expanded || isOpen;
    });
    editors.classList.toggle('barama-catalog-v117a8-expanded', expanded);
  }

  entries.forEach(({ button, panel }) => {
    panel.addEventListener('toggle', update);
    button.addEventListener('click', () => {
      const openNext = !panel.open;
      if (openNext) {
        // Keep only one form visible; preserve field values when switching.
        entries.forEach(entry => { if (entry.panel !== panel) entry.panel.open = false; });
      }
      panel.open = openNext;
      update();
      if (!openNext) return;
      panel.scrollIntoView({
        block:'start',
        behavior: window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
      });
      const field = panel.querySelector('input[name="name"]');
      if (field) field.focus({preventScroll:true});
    });
  });

  editors.classList.add('barama-catalog-v117a8-enhanced');
  update();
})();

};

/* ===== media-library-index ===== */
modules["media-library-index"]=function(__baramaScript){
/* BARAMA_MEDIA_LIBRARY_INDEX_V137 */
(() => {
    'use strict';

    let modal = null;
    let stage = null;
    let title = null;
    let meta = null;
    let openOriginal = null;
    let note = null;
    let lastTrigger = null;

    const clearStage = () => {
        if (!stage) return;

        stage.querySelectorAll('video, audio').forEach((media) => {
            try {
                media.pause();
                media.removeAttribute('src');
                media.load();
            } catch (_) {
                // Browser-specific media teardown differences are harmless.
            }
        });

        stage.replaceChildren();
    };

    const hide = () => {
        if (!modal) return;

        clearStage();
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('workspace-media-preview-open');

        if (lastTrigger) {
            lastTrigger.focus({preventScroll: true});
        }

        lastTrigger = null;
    };

    const ensureModal = () => {
        if (modal) return;

        modal = document.createElement('div');
        modal.className = 'workspace-media-modal';
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        const backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.className = 'workspace-media-modal__backdrop';
        backdrop.setAttribute('aria-label', 'Close media preview');

        const dialog = document.createElement('section');
        dialog.className = 'workspace-media-modal__dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute(
            'aria-labelledby',
            'workspace-media-preview-title'
        );

        const header = document.createElement('header');
        header.className = 'workspace-media-modal__header';

        const headingGroup = document.createElement('div');

        const eyebrow = document.createElement('span');
        eyebrow.className = 'workspace-media-modal__eyebrow';
        eyebrow.textContent = 'Media Library Preview';

        title = document.createElement('h2');
        title.id = 'workspace-media-preview-title';
        title.textContent = 'Media preview';

        meta = document.createElement('p');

        headingGroup.append(eyebrow, title, meta);

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'workspace-media-modal__close';
        close.setAttribute('aria-label', 'Close media preview');
        close.textContent = '×';

        header.append(headingGroup, close);

        stage = document.createElement('div');
        stage.className = 'workspace-media-modal__stage';

        const footer = document.createElement('footer');
        footer.className = 'workspace-media-modal__footer';

        note = document.createElement('span');
        note.textContent = 'Preview is available only to authorized Media Library administrators.';

        openOriginal = document.createElement('a');
        openOriginal.className = 'btn btn-primary';
        openOriginal.href = '#';
        openOriginal.target = '_blank';
        openOriginal.rel = 'noopener';
        openOriginal.textContent = 'Open original';

        footer.append(note, openOriginal);
        dialog.append(header, stage, footer);
        modal.append(backdrop, dialog);
        document.body.append(modal);

        backdrop.addEventListener('click', hide);
        close.addEventListener('click', hide);
    };

    const makeInfoPanel = (kind, name, mime, url) => {
        const wrapper = document.createElement('div');
        wrapper.className = kind === 'audio'
            ? 'workspace-media-modal__audio'
            : 'workspace-media-modal__document';

        const mark = document.createElement('span');
        mark.className = 'workspace-media-modal__mark';
        mark.textContent = kind === 'audio' ? 'Audio' : 'File';

        const strong = document.createElement('strong');
        strong.textContent = name;

        const description = document.createElement('p');
        description.textContent = kind === 'audio'
            ? 'Listen to this protected Media Library audio asset.'
            : `Inline browser preview is not available for ${mime || 'this document type'}. Use Open original to view or download it.`;

        wrapper.append(mark, strong, description);

        if (kind === 'audio') {
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.preload = 'metadata';
            audio.src = url;
            wrapper.append(audio);
        }

        return wrapper;
    };

    const render = (trigger) => {
        ensureModal();

        const url = trigger.dataset.mediaUrl || '';
        const name = trigger.dataset.mediaName || 'Media preview';
        const mime = trigger.dataset.mediaMime || '';
        const kind = trigger.dataset.mediaKind || 'document';
        const alt = trigger.dataset.mediaAlt || '';
        const dimensions = trigger.dataset.mediaDimensions || '';

        clearStage();

        title.textContent = name;
        meta.textContent = [mime, dimensions].filter(Boolean).join(' · ');
        openOriginal.href = url;
        note.textContent = kind === 'document'
            ? 'This protected file will use your browser’s document handling.'
            : 'Preview is available only through the authenticated Admin Media Library route.';

        let content;

        if (kind === 'image') {
            content = document.createElement('img');
            content.src = url;
            content.alt = alt || name;
            content.decoding = 'async';
        } else if (kind === 'video') {
            content = document.createElement('video');
            content.src = url;
            content.controls = true;
            content.preload = 'metadata';
            content.playsInline = true;
        } else if (kind === 'pdf') {
            content = document.createElement('iframe');
            content.src = url;
            content.title = `Preview ${name}`;
        } else {
            content = makeInfoPanel(kind, name, mime, url);
        }

        stage.append(content);
    };

    const show = (trigger) => {
        lastTrigger = trigger;
        render(trigger);

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('workspace-media-preview-open');

        modal
            .querySelector('.workspace-media-modal__close')
            ?.focus({preventScroll: true});
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-media-preview]');
        if (!trigger) return;

        event.preventDefault();
        show(trigger);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            hide();
        }
    });

    // BARAMA_MEDIA_THUMBNAIL_FALLBACK_V101: leave text fallback visible
    // if the browser cannot load an authenticated media thumbnail.
    document.addEventListener('error', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLImageElement)) return;
        if (!target.closest('.barama-media-focus-thumb')) return;
        target.remove();
    }, true);
})();

};

/* ===== media-library-upload ===== */
modules["media-library-upload"]=function(__baramaScript){
/* BARAMA_MEDIA_LIBRARY_UPLOAD_V134 */
(() => {
    'use strict';
    const root = document.querySelector('[data-media-upload]');
    if (!root) return;
    const form = root.querySelector('[data-media-upload-form]');
    const dropzone = root.querySelector('[data-media-dropzone]');
    const input = root.querySelector('[data-media-input]');
    const choose = root.querySelector('[data-media-choose]');
    const clear = root.querySelector('[data-media-clear]');
    const selection = root.querySelector('[data-media-selection]');
    const list = root.querySelector('[data-media-file-list]');
    const count = root.querySelector('[data-media-count]');
    const submit = root.querySelector('[data-media-submit]');
    const submitLabel = root.querySelector('[data-media-submit-label]');
    if (!form || !dropzone || !input || !selection || !list) return;

    const maxFiles = Number(form.dataset.mediaMaxFiles || 20);
    const maxBytes = Number(form.dataset.mediaMaxBytes || 52428800);
    const clientError = root.querySelector('[data-media-upload-client-error]');
    const allowed = new Set((input.accept || '').toLowerCase().split(',').map(v => v.trim()));
    const setError = (message) => {
        if (!clientError) return;
        clientError.textContent = message;
        clientError.hidden = !message;
    };
    const ext = (name) => String(name || '').split('.').pop().toLowerCase();
    const category = (file) => {
        const type = String(file.type || '').toLowerCase();
        if (type.startsWith('image/')) return 'image';
        if (type === 'application/pdf' || ext(file.name) === 'pdf') return 'pdf';
        if (type.startsWith('video/')) return 'video';
        if (type.startsWith('audio/')) return 'music';
        return 'doc';
    };
    const sizeLabel = (bytes) => Number(bytes || 0) >= 1048576
        ? `${(Number(bytes) / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Number(bytes) / 1024).toFixed(1)} KB`;

    const render = () => {
        setError('');
        const files = Array.from(input.files || []);
        list.innerHTML = '';
        selection.hidden = files.length === 0;
        if (count) count.textContent = `${files.length} file${files.length === 1 ? '' : 's'}`;
        files.forEach((file) => {
            const row = document.createElement('div');
            row.className = 'media-upload__file';
            const badge = document.createElement('span');
            badge.className = 'media-upload__file-badge';
            badge.textContent = category(file);
            const info = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = file.name;
            const mime = document.createElement('small');
            mime.textContent = file.type || 'Detected on server';
            info.append(name, mime);
            const size = document.createElement('span');
            size.textContent = sizeLabel(file.size);
            row.append(badge, info, size);
            list.append(row);
        });
    };

    const validSelection = () => {
        const files = Array.from(input.files || []);
        if (!files.length) return 'Choose at least one file to upload.';
        if (files.length > maxFiles) return `Choose no more than ${maxFiles} files per batch.`;
        for (const file of files) {
            if (!file.size || file.size > maxBytes) return `${file.name}: file is empty or exceeds the per-file limit.`;
            if (!allowed.has(`.${ext(file.name)}`)) return `${file.name}: unsupported file extension.`;
        }
        return '';
    };

    const openPicker = () => input.click();
    choose?.addEventListener('click', openPicker);
    dropzone.addEventListener('click', (event) => {
        // A programmatic input.click() bubbles back through the dropzone.
        // Ignore the native file input to prevent recursive picker invocation.
        if (event.target !== input && !event.target.closest('button')) openPicker();
    });
    dropzone.addEventListener('keydown', (event) => {
        if (event.target === dropzone && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault(); openPicker();
        }
    });
    ['dragenter', 'dragover'].forEach((name) => dropzone.addEventListener(name, (event) => {
        event.preventDefault(); dropzone.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((name) => dropzone.addEventListener(name, (event) => {
        event.preventDefault(); dropzone.classList.remove('is-dragging');
    }));
    dropzone.addEventListener('drop', (event) => {
        const files = Array.from(event.dataTransfer?.files || []);
        if (!files.length) return;
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        render();
        const error = validSelection();
        if (error) setError(error);
    });
    input.addEventListener('change', render);
    clear?.addEventListener('click', () => { input.value = ''; render(); });
    form.addEventListener('submit', (event) => {
        const error = validSelection();
        if (error) {
            event.preventDefault();
            setError(error);
            clientError?.scrollIntoView({block: 'nearest'});
            return;
        }
        setError('');
        // The global change-passcode dialog may have intercepted this submit.
        // Disable the button only when a real POST will proceed.
        if (event.defaultPrevented) return;
        if (submit) submit.disabled = true;
        if (submitLabel) submitLabel.textContent = 'Uploading…';
    });
    render();
})();

};

/* ===== media-library-upload-limits ===== */
modules["media-library-upload-limits"]=function(__baramaScript){
/* BARAMA_MEDIA_UPLOAD_SIZE_GUARD_V102 — no additional JavaScript dependencies. */
(() => {
    'use strict';
    const form = document.querySelector('[data-media-upload-form][data-size-file-limit]');
    if (!form) return;
    const input = form.querySelector('[data-media-input]');
    const error = form.querySelector('[data-size-error]');
    const submit = form.querySelector('[data-media-submit]');
    if (!input || !error) return;

    const maxFiles = Number(form.dataset.sizeMaxFiles || 20);
    const fileLimit = Number(form.dataset.sizeFileLimit || 0);
    const requestBudget = Number(form.dataset.sizeRequestBudget || 0);
    const size = bytes => `${(bytes / 1048576).toFixed(1)} MB`;
    let lastError = '';
    const validate = () => {
        const selected = Array.from(input.files || []);
        if (!selected.length) return 'Choose at least one file to upload.';
        if (selected.length > maxFiles) return `You selected ${selected.length} files. Maximum: ${maxFiles} files per upload.`;
        if (!Number.isFinite(fileLimit) || fileLimit < 1)
            return 'This server currently permits no uploads. Ask your administrator to check PHP upload limits.';
        const oversized = selected.find(file => !file.size || file.size > fileLimit);
        if (oversized) return `${oversized.name}: ${size(oversized.size)} exceeds this server’s per-file limit (${size(fileLimit)}). The file was NOT submitted.`;
        const total = selected.reduce((bytes, file) => bytes + file.size, 0);
        if (Number.isFinite(requestBudget) && total > requestBudget)
            return `The selected files total ${size(total)}, exceeding the server’s safe request budget (${size(requestBudget)}). Upload fewer files at once.`;
        return '';
    };
    const paint = message => {
        if (message === lastError) return;
        lastError = message;
        error.textContent = message;
        error.hidden = !message;
        error.classList.toggle('is-visible', Boolean(message));
        if (submit) submit.setAttribute('aria-describedby', message ? 'barama-upload-limit-error-v102' : '');
    };
    error.id = 'barama-upload-limit-error-v102';
    input.addEventListener('change', () => paint(input.files?.length ? validate() : ''));
    form.querySelector('[data-media-clear]')?.addEventListener('click', () => paint(''));
    form.querySelector('[data-media-dropzone]')?.addEventListener('drop', event => {
        const count = event.dataTransfer?.files?.length || 0;
        if (count > maxFiles) {
            paint(`You dropped ${count} files. Maximum: ${maxFiles} files per upload.`);
        } else {
            paint(validate());
        }
    });
    form.addEventListener('submit', event => {
        const message = validate();
        if (!message) { paint(''); return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        paint(message);
        error.scrollIntoView({behavior:'smooth',block:'nearest'});
    }, true);
})();

};

/* ===== invoice-template ===== */
modules["invoice-template"]=function(__baramaScript){
(() => {
    const modal = document.querySelector('[data-invoice-template-modal]');
    if (!modal) return;

    const form = document.querySelector('[data-invoice-template-form]');
    const previewShell = modal.querySelector('.invoice-template-preview-shell');
    const preview = modal.querySelector('[data-invoice-template-preview]');
    let dirty = false;

    const open = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
    };
    const close = (force = false) => {
        if (!force && dirty && !window.confirm('Discard unsaved invoice template changes?')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
        dirty = false;
    };

    document.querySelectorAll('[data-invoice-template-open]').forEach((button) => button.addEventListener('click', open));
    modal.querySelectorAll('[data-invoice-template-close]').forEach((button) => button.addEventListener('click', () => close()));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('is-open')) close(); });

    modal.querySelectorAll('[data-template-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.dataset.templateTab;
            modal.querySelectorAll('[data-template-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
            modal.querySelectorAll('[data-template-pane]').forEach((pane) => pane.classList.toggle('is-active', pane.dataset.templatePane === key));
        });
    });

    const updatePreview = (source) => {
        const key = source.dataset.previewSource;
        if (!key) return;
        const value = source.value;
        if (key === 'accent_color') {
            previewShell?.style.setProperty('--invoice-accent', value);
            const output = modal.querySelector('[data-accent-value]');
            if (output) output.textContent = value.toUpperCase();
            return;
        }
        if (key === 'logo_url') {
            const img = modal.querySelector('[data-preview-logo]');
            if (img) { img.src = value; img.hidden = value.trim() === ''; }
            return;
        }
        if (key === 'invoice_prefix') {
            const number = modal.querySelector('[data-preview-invoice-number]');
            if (number) number.textContent = `${value || 'INV'}-2026-0001`;
        }
        modal.querySelectorAll(`[data-preview-target="${CSS.escape(key)}"]`).forEach((target) => { target.textContent = value; });
    };

    form?.querySelectorAll('[data-preview-source]').forEach((source) => source.addEventListener('input', () => { dirty = true; updatePreview(source); }));
    form?.querySelectorAll('input, textarea, select').forEach((field) => field.addEventListener('change', () => { dirty = true; }));
    form?.addEventListener('submit', () => { dirty = false; });

    modal.querySelectorAll('[data-template-token]').forEach((button) => {
        button.addEventListener('click', async () => {
            const token = button.dataset.templateToken || '';
            try { await navigator.clipboard.writeText(token); }
            catch (_) { window.prompt('Copy variable', token); }
        });
    });

    document.querySelector('[data-template-reset]')?.addEventListener('click', (event) => {
        if (!window.confirm('Reset the invoice template to Barama defaults?')) event.preventDefault();
    });

    modal.querySelector('[data-template-print]')?.addEventListener('click', () => {
        if (!preview) return;
        const win = window.open('', '_blank', 'noopener,noreferrer,width=1000,height=800');
        if (!win) return;
        win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Invoice Template Preview</title><style>body{margin:0;padding:32px;background:#eef2f7;font-family:Inter,system-ui,sans-serif}.invoice-template-preview{margin:0 auto}@media print{body{padding:0;background:#fff}.invoice-template-preview{box-shadow:none;max-width:none;width:auto}}</style></head><body>${preview.outerHTML}</body></html>`);
        win.document.close();
        win.focus();
        window.setTimeout(() => win.print(), 250);
    });

    window.setTimeout(() => document.querySelectorAll('[data-invoice-template-toast]').forEach((toast) => toast.remove()), 5000);
    const params = new URLSearchParams(window.location.search);
    if (params.has('invoice_template')) { open(); window.history.replaceState({}, '', window.location.pathname); }
})();

};

/* ===== invoice-template-logo-upload ===== */
modules["invoice-template-logo-upload"]=function(__baramaScript){

(() => {
    const modal = document.querySelector('[data-invoice-template-modal]');
    const form = document.querySelector('[data-invoice-template-form]');
    if (!modal || !form) return;

    const dropzone = modal.querySelector('[data-logo-dropzone]');
    const input = modal.querySelector('[data-logo-file]');
    const browse = modal.querySelector('[data-logo-browse]');
    const clear = modal.querySelector('[data-logo-clear]');
    const status = modal.querySelector('[data-logo-upload-status]');
    const urlInput = form.querySelector('input[name="logo_url"]');
    const previewLogo = modal.querySelector('[data-preview-logo]');

    if (!dropzone || !input || !urlInput) return;

    const maxBytes = 5 * 1024 * 1024;
    const allowed = new Set([
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif',
    ]);

    let objectUrl = null;

    const setStatus = (message, state = '') => {
        if (status) {
            status.textContent = message;
            status.classList.remove('is-ready', 'is-error');
            if (state) status.classList.add(state);
        }
        clear?.classList.toggle('is-visible', state === 'is-ready');
    };

    const releaseObjectUrl = () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };

    const validate = (file) => {
        if (!file) return 'No file selected.';
        if (!allowed.has(file.type)) {
            return 'Use PNG, JPG/JPEG, WebP or GIF.';
        }
        if (file.size > maxBytes) {
            return 'Logo must be 5 MB or smaller.';
        }
        return '';
    };

    const assignFile = (file) => {
        const error = validate(file);
        if (error) {
            setStatus(error, 'is-error');
            return false;
        }

        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
        } catch (_) {
            // File picker selection already populated input.files.
        }

        releaseObjectUrl();
        objectUrl = URL.createObjectURL(file);

        if (previewLogo) {
            previewLogo.src = objectUrl;
            previewLogo.hidden = false;
        }

        setStatus(
            `${file.name} · ${(file.size / 1024).toFixed(0)} KB · ready to upload when you save`,
            'is-ready'
        );

        // Existing v1.5.0 listener on logo_url marks the form dirty.
        urlInput.dispatchEvent(new Event('change', { bubbles: true }));

        return true;
    };

    browse?.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (file) assignFile(file);
    });

    ['dragenter', 'dragover'].forEach((name) => {
        dropzone.addEventListener(name, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'dragend'].forEach((name) => {
        dropzone.addEventListener(name, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.remove('is-dragover');

        const file = event.dataTransfer?.files?.[0];
        if (file) assignFile(file);
    });

    clear?.addEventListener('click', () => {
        input.value = '';
        releaseObjectUrl();

        const currentUrl = urlInput.value.trim();
        if (previewLogo) {
            previewLogo.src = currentUrl;
            previewLogo.hidden = currentUrl === '';
        }

        setStatus(
            'No upload selected. The Logo URL / site path field is still active.'
        );
        urlInput.dispatchEvent(new Event('change', { bubbles: true }));
    });

    form.addEventListener('submit', (event) => {
        const file = input.files?.[0];
        if (!file) return;

        const error = validate(file);
        if (error) {
            event.preventDefault();
            setStatus(error, 'is-error');
        }
    });

    window.addEventListener('beforeunload', releaseObjectUrl, { once: true });
})();

};

/* ===== admin-roles-permissions ===== */
modules["admin-roles-permissions"]=function(__baramaScript){
/* BARAMA_ADMIN_ROLES_PERMISSIONS_V135 */
(() => {
  'use strict';
  const root = document.querySelector('[data-rbac-admin]');
  if (!root) return;
  const closeAll = () => {
    root.querySelectorAll('[data-rbac-modal]').forEach((modal) => { modal.hidden = true; });
    document.body.classList.remove('rbac-modal-open');
  };
  root.querySelectorAll('[data-rbac-open]').forEach((button) => {
    button.addEventListener('click', () => {
      closeAll();
      const slug = button.getAttribute('data-rbac-open');
      const modal = root.querySelector(`[data-rbac-modal="${CSS.escape(slug || '')}"]`);
      if (!modal) return;
      modal.hidden = false;
      document.body.classList.add('rbac-modal-open');
      const focusTarget = modal.querySelector('input:not([disabled]), button:not([disabled])');
      if (focusTarget) focusTarget.focus();
    });
  });
  root.querySelectorAll('[data-rbac-close]').forEach((button) => button.addEventListener('click', closeAll));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeAll(); });
  if (window.history && window.history.replaceState) {
    const url = new URL(window.location.href);
    ['saved', 'error', 'role'].forEach((key) => url.searchParams.delete(key));
    window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
  }
})();

};

/* ===== seo-center ===== */
modules["seo-center"]=function(__baramaScript){
/* BARAMA_SEO_CENTER_PREMIUM_V138 */
(() => {
    'use strict';

    const inspector = document.querySelector('[data-seo-inspector]');
    if (!inspector) return;

    const title = inspector.querySelector('[data-seo-title]');
    const path = inspector.querySelector('[data-seo-path]');
    const score = inspector.querySelector('[data-seo-score]');
    const label = inspector.querySelector('[data-seo-label]');
    const checklist = inspector.querySelector('[data-seo-checklist]');
    const issues = inspector.querySelector('[data-seo-issues]');
    const serpPath = inspector.querySelector('[data-seo-serp-path]');
    const serpTitle = inspector.querySelector('[data-seo-serp-title]');
    const publicLink = inspector.querySelector('[data-seo-public]');
    const editLink = inspector.querySelector('[data-seo-edit]');
    const copy = inspector.querySelector('[data-seo-copy]');

    let current = null;
    let lastTrigger = null;

    const stateLabel = (value) => {
        if (!value || value === 'n/a') return 'Not applicable';
        return String(value)
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    };

    const goodState = (value) =>
        ['ready', 'indexable', 'n/a'].includes(String(value || ''));

    const checklistItem = (name, value) => {
        const row = document.createElement('div');
        row.className = `seo-inspector__check ${goodState(value) ? '' : 'is-attention'}`.trim();

        const dot = document.createElement('i');
        const text = document.createElement('div');
        const strong = document.createElement('strong');
        const small = document.createElement('small');

        strong.textContent = name;
        small.textContent = stateLabel(value);

        text.append(strong, small);
        row.append(dot, text);

        return row;
    };

    const render = (payload) => {
        current = payload;

        title.textContent = payload.title || 'SEO record';
        path.textContent = payload.path || '—';
        score.textContent = String(payload.score ?? 0);
        label.textContent = payload.label || '—';
        serpPath.textContent = payload.path || '/';
        serpTitle.textContent = payload.title || 'Untitled public record';

        publicLink.href = payload.path || '#';
        editLink.href = payload.edit_url || '/dashboard/seo';

        checklist.replaceChildren(
            checklistItem('Indexability', payload.index),
            checklistItem('Description', payload.description),
            checklistItem('Social image', payload.image),
            checklistItem('Image alt text', payload.alt),
        );

        issues.replaceChildren();

        const issueList = Array.isArray(payload.issues)
            ? payload.issues
            : [];

        if (!issueList.length) {
            const ready = document.createElement('span');
            ready.className = 'is-empty';
            ready.textContent = 'No priority coverage issue detected for this record.';
            issues.append(ready);
        } else {
            issueList.forEach((issue) => {
                const row = document.createElement('div');
                row.className = 'seo-inspector__issue';

                const text = document.createElement('div');
                const strong = document.createElement('strong');
                const small = document.createElement('small');

                strong.textContent = issue.label || 'SEO attention';
                small.textContent = issue.detail || '';

                text.append(strong, small);
                row.append(text);
                issues.append(row);
            });
        }
    };

    const show = (trigger, payload) => {
        lastTrigger = trigger;
        render(payload);
        inspector.hidden = false;
        inspector.setAttribute('aria-hidden', 'false');
        document.body.classList.add('seo-inspector-open');

        inspector
            .querySelector('.seo-inspector__close')
            ?.focus({preventScroll: true});
    };

    const hide = () => {
        inspector.hidden = true;
        inspector.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('seo-inspector-open');

        lastTrigger?.focus({preventScroll: true});
        lastTrigger = null;
        current = null;
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-seo-inspect]');

        if (trigger) {
            event.preventDefault();

            try {
                const payload = JSON.parse(
                    trigger.getAttribute('data-seo-inspect') || '{}'
                );
                show(trigger, payload);
            } catch (_) {
                return;
            }
        }

        if (event.target.closest('[data-seo-close]')) {
            hide();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !inspector.hidden) {
            hide();
        }
    });

    copy?.addEventListener('click', async () => {
        if (!current?.path) return;

        const absolute = new URL(current.path, window.location.origin).href;

        try {
            await navigator.clipboard.writeText(absolute);
            const original = copy.textContent;
            copy.textContent = 'Copied';
            setTimeout(() => {
                copy.textContent = original;
            }, 1200);
        } catch (_) {
            window.prompt('Copy public URL', absolute);
        }
    });
})();

};

/* ===== admin-global-locations ===== */
modules["admin-global-locations"]=function(__baramaScript){
/* BARAMA_GLOBAL_LOCATIONS_V110 */
(() => {
    'use strict';

    const root = document.querySelector('[data-global-location-manager]');
    if (!root) return;

    const search = root.querySelector('[data-country-search]');
    const status = root.querySelector('[data-country-status-filter]');
    const rows = Array.from(root.querySelectorAll('[data-country-row]'));
    const empty = root.querySelector('[data-country-empty]');

    const applyFilter = () => {
        const query = String(search?.value || '').trim().toLowerCase();
        const wantedStatus = String(status?.value || 'all');
        let visible = 0;

        rows.forEach((row) => {
            const haystack = String(row.dataset.countryName || '');
            const rowStatus = String(row.dataset.countryStatus || 'disabled');
            const matchesSearch = query === '' || haystack.includes(query);
            const matchesStatus = wantedStatus === 'all' || wantedStatus === rowStatus;
            const show = matchesSearch && matchesStatus;
            row.hidden = !show;
            if (show) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
    };

    search?.addEventListener('input', applyFilter);
    status?.addEventListener('change', applyFilter);

    root.querySelectorAll('.barama-location-choice input[type="radio"]').forEach((input) => {
        input.addEventListener('change', () => {
            root.querySelectorAll('.barama-location-choice').forEach((label) => label.classList.remove('is-active'));
            input.closest('.barama-location-choice')?.classList.add('is-active');
        });
    });
})();

};

/* ===== turnstile-admin-modal ===== */
modules["turnstile-admin-modal"]=function(__baramaScript){
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

};

/* ===== admin-settings-security-factors ===== */
modules["admin-settings-security-factors"]=function(__baramaScript){
/* BARAMA_ADMIN_SECURITY_FACTORS_V133 */
(() => {
    'use strict';

    const codeGroups = new WeakMap();

    const initializeCodeGroup = (group) => {
        if (!(group instanceof HTMLElement) || codeGroups.has(group)) return;
        const digits = Array.from(group.querySelectorAll('[data-security-code-digit]'));
        const hidden = group.querySelector('[data-security-code-value]');
        if (!(hidden instanceof HTMLInputElement) || digits.length !== 6) return;

        const sync = () => {
            const value = digits.map((input) => input.value.replace(/\D/g, '').slice(0, 1)).join('');
            hidden.value = value;
            group.classList.toggle('is-complete', value.length === 6);
            if (value.length === 6) group.classList.remove('is-error');
            return value;
        };

        const fill = (value) => {
            const numeric = String(value || '').replace(/\D/g, '').slice(0, 6);
            digits.forEach((input, index) => { input.value = numeric[index] || ''; });
            sync();
            const next = digits[Math.min(numeric.length, 5)];
            next?.focus();
            if (numeric.length === 6) digits[5]?.select();
        };

        digits.forEach((input, index) => {
            input.addEventListener('input', () => {
                const numeric = input.value.replace(/\D/g, '');
                input.value = numeric.slice(-1);
                sync();
                if (input.value !== '' && index < digits.length - 1) digits[index + 1].focus();
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && input.value === '' && index > 0) {
                    digits[index - 1].focus();
                    digits[index - 1].value = '';
                    sync();
                }
                if (event.key === 'ArrowLeft' && index > 0) {
                    event.preventDefault();
                    digits[index - 1].focus();
                }
                if (event.key === 'ArrowRight' && index < digits.length - 1) {
                    event.preventDefault();
                    digits[index + 1].focus();
                }
            });
        });

        group.addEventListener('paste', (event) => {
            const text = event.clipboardData?.getData('text') || '';
            if (!/\d/.test(text)) return;
            event.preventDefault();
            fill(text);
        });

        codeGroups.set(group, { digits, hidden, sync, fill });
    };

    document.querySelectorAll('[data-security-code-group]').forEach(initializeCodeGroup);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-security-code-form]')) return;
        const groups = Array.from(form.querySelectorAll('[data-security-code-group]'));
        const invalid = groups.find((group) => {
            const state = codeGroups.get(group);
            return !state || !/^\d{6}$/.test(state.sync());
        });
        const error = form.querySelector('[data-security-code-form-error]');
        if (!invalid) {
            if (error) error.hidden = true;
            return;
        }
        event.preventDefault();
        invalid.classList.add('is-error');
        if (error) {
            error.textContent = 'Complete all six digits before continuing.';
            error.hidden = false;
        }
        const state = codeGroups.get(invalid);
        state?.digits.find((input) => input.value === '')?.focus() || state?.digits[0]?.focus();
    }, true);

    document.addEventListener('click', async (event) => {
        const copy = event.target.closest('[data-security-copy]');
        if (copy) {
            const key = copy.getAttribute('data-security-copy');
            const source = document.querySelector(`[data-security-copy-source="${CSS.escape(key || '')}"]`);
            if (source) {
                const value = (source.textContent || '').trim();
                if (value) {
                    try {
                        await navigator.clipboard.writeText(value);
                        const original = copy.textContent;
                        copy.textContent = 'Copied';
                        window.setTimeout(() => { copy.textContent = original; }, 1200);
                    } catch (error) {
                        window.getSelection()?.selectAllChildren(source);
                    }
                }
            }
            return;
        }

        const recoveryRoot = event.target.closest('[data-security-recovery]');
        if (!recoveryRoot) return;
        const codes = Array.from(recoveryRoot.querySelectorAll('[data-security-recovery-list] code'))
            .map((node) => (node.textContent || '').trim())
            .filter(Boolean);
        if (codes.length === 0) return;

        if (event.target.closest('[data-security-copy-recovery]')) {
            const button = event.target.closest('[data-security-copy-recovery]');
            try {
                await navigator.clipboard.writeText(codes.join('\n'));
                const original = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(() => { button.textContent = original; }, 1200);
            } catch (error) {
                // Browser clipboard permissions can be denied; codes remain visible for manual copy.
            }
        }

        if (event.target.closest('[data-security-download-recovery]')) {
            const blob = new Blob([
                'Barama MFA recovery codes\n',
                'Store these codes securely. Each code can be used once.\n\n',
                codes.join('\n'),
                '\n',
            ], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'barama-mfa-recovery-codes.txt';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        }
    });

    document.querySelectorAll('[data-security-qr]').forEach((target) => {
        const uri = target.getAttribute('data-security-qr-uri') || '';
        if (!uri) return;
        try {
            if (!window.BaramaQRCode?.render) throw new Error('QR renderer unavailable');
            window.BaramaQRCode.render(target, uri);
        } catch (error) {
            target.classList.add('is-error');
            target.textContent = 'QR code could not be rendered. Use the manual setup key instead.';
        }
    });

    const params = new URLSearchParams(window.location.search);
    if (params.get('security_modal') === 'mfa') {
        const mfa = document.getElementById('barama-security-mfa');
        if (mfa && typeof mfa.showPopover === 'function') {
            window.setTimeout(() => {
                try { mfa.showPopover(); } catch (error) { /* already open */ }
            }, 80);
            params.delete('security_modal');
            const query = params.toString();
            const clean = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
            window.history.replaceState({}, '', clean);
        }
    }
})();

};

/* ===== admin-settings-security-protection ===== */
modules["admin-settings-security-protection"]=function(__baramaScript){
/* BARAMA_ADMIN_SECURITY_PROTECTION_V134 */
(() => {
    'use strict';
    const params = new URLSearchParams(window.location.search);
    const modal = params.get('security_modal');
    if (modal && ['spam','hack','right-click'].includes(modal)) {
        const target = document.getElementById(`barama-security-${modal}`);
        if (target && typeof target.showPopover === 'function') {
            window.setTimeout(() => {
                try { target.showPopover(); } catch (error) { /* already open */ }
            }, 80);
        }
    }

    let changed = false;
    for (const key of ['saved','message','error','security_modal']) {
        if (params.has(key)) {
            params.delete(key);
            changed = true;
        }
    }
    if (changed) {
        const query = params.toString();
        const clean = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
        window.history.replaceState({}, '', clean);
    }
})();

};

/* ===== barama-publisher-license-ux ===== */
modules["barama-publisher-license-ux"]=function(__baramaScript){
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
    'Installed license recognized': 'Your stored activated license is recognized. Publisher verification is optional.',
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
    buttonText.textContent = currentState.textContent.trim() === 'Installed license recognized'
      ? 'Verify publisher (optional)'
      : host.dataset.verified === '1' ? 'Verify again' : 'Check license';
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
      let explanation = 'The new check could not be completed in this browser. Refresh to restore the last recorded license result before considering another check.';
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

};

/* ===== barama-auto-updater-premium ===== */
modules["barama-auto-updater-premium"]=function(__baramaScript){
/* Barama Auto Updater v1.2.1: presentation only. No licensing network calls or storage. */
(() => {
  'use strict';
  const scope = document.querySelector('.bsys-auto-premium');
  const table = scope?.querySelector('[data-premium-table]');
  const host = scope?.querySelector('[data-publisher-control]');
  if (!scope || !table || !host) return;
  const body = table.tBodies[0];
  const triggers = Array.from(table.querySelectorAll('[data-detail-target]'));
  const itemRows = Array.from(body.querySelectorAll('tr.bsys-premium-item'));
  const license = body.querySelector('tr[data-premium-item="license"]');
  const state = host.querySelector('[data-publisher-state]');
  const progress = host.querySelector('[data-publisher-progress]');
  const lifecycle = host.querySelector('[data-publisher-lifecycle]');
  const tag = license?.querySelector('[data-premium-license-tag]');
  const summary = license?.querySelector('[data-premium-license-summary]');
  if (!body || !license || !state || !tag || !summary) return;

  const detailFor = (button) => {
    const id = button.getAttribute('data-detail-target') || '';
    const row = id ? document.getElementById(id) : null;
    return row && body.contains(row) ? row : null;
  };
  const close = (button) => {
    const row = detailFor(button);
    if (row) row.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  };
  triggers.forEach(button => button.addEventListener('click', () => {
    const row = detailFor(button);
    if (!row) return;
    const opening = button.getAttribute('aria-expanded') !== 'true';
    triggers.forEach(close);
    row.hidden = !opening;
    button.setAttribute('aria-expanded', opening ? 'true' : 'false');
  }));
  table.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const active = triggers.find(button => button.getAttribute('aria-expanded') === 'true');
    if (active) { close(active); active.focus(); event.preventDefault(); }
  });

  const reorder = () => {
    // Each item and its associated Detail row must remain adjacent; keep stable tie order.
    const sorted = itemRows.map((item, index) => ({item, index, priority: Number(item.dataset.priority || 0)}))
      .sort((a, b) => (b.priority - a.priority) || (a.index - b.index));
    // Avoid unnecessary DOM moves (and losing focused controls) if already in order.
    const current = Array.from(body.querySelectorAll('tr.bsys-premium-item'));
    if (sorted.every((entry, index) => entry.item === current[index])) return;
    const fragment = document.createDocumentFragment();
    sorted.forEach(({item}) => {
      const id = item.querySelector('[data-detail-target]')?.getAttribute('data-detail-target');
      const detail = id ? document.getElementById(id) : null;
      fragment.append(item);
      if (detail && body.contains(detail)) fragment.append(detail);
    });
    body.append(fragment);
  };
  const sync = () => {
    const name = (state.textContent || '').trim();
    const installed = name === 'Installed license recognized';
    const verified = (name === 'Active license verified' || installed) && host.dataset.verified === '1';
    const waiting = name === 'Wait before retrying' || name === 'Publisher temporarily unavailable';
    const text = tag.querySelector('[data-publisher-summary]');
    if (text) text.textContent = installed && verified ? 'Activated' : verified ? 'Verified' : name || 'Not checked';
    tag.classList.remove('bsys-premium-tag--good', 'bsys-premium-tag--needs', 'bsys-premium-tag--neutral');
    tag.classList.add(verified ? 'bsys-premium-tag--good' : waiting ? 'bsys-premium-tag--neutral' : 'bsys-premium-tag--needs');
    // Server-provided persisted evidence or a recognized installed activation
    // is authoritative for display; the browser never caches license state.
    const message = installed && verified ? 'Installed activation recognized; optional publisher check'
      : verified ? 'Last publisher verification saved for this installation'
      : name === 'Not checked' ? 'Publisher validation not confirmed'
      : waiting ? 'Verification needs retry or connection review'
      : 'Publisher verification needs attention';
    if (summary.firstChild?.nodeType === Node.TEXT_NODE) summary.firstChild.textContent = message;
    const remote = summary.querySelector('[data-publisher-readiness-state]');
    if (remote) remote.textContent = name || 'Not checked';
    const priority = verified ? '20' : '200';
    if (license.dataset.priority !== priority) license.dataset.priority = priority;
    if (host.dataset.checking !== '1') {
      // The old browser verifier sets lifecycle stage=complete but leaves it visible.
      // Hide it only after its request has completed; preserve the live progress UI.
      if (lifecycle) lifecycle.hidden = true;
      if (progress) progress.hidden = true;
      reorder();
    }
  };
  sync();
  if (window.MutationObserver) {
    const observer = new MutationObserver(sync);
    observer.observe(state, {childList:true, characterData:true, subtree:true});
    observer.observe(host, {attributes:true, attributeFilter:['data-verified', 'data-checking']});
    window.addEventListener('pagehide', () => observer.disconnect(), {once:true});
  }
})();

};

const seen=new Set();
for(const marker of document.querySelectorAll('script[type="application/x-barama-module"][data-barama-module]')){
  const key=marker.getAttribute('data-barama-module');
  if(seen.has(key)||!Object.prototype.hasOwnProperty.call(modules,key))continue;
  seen.add(key);
  try{modules[key](marker);}catch(error){console.error('[Barama asset module]',key,error);}
}
})();
