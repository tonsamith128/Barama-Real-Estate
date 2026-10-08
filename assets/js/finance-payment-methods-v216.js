/* BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_V112 */
/* BARAMA_PAYMENT_METHODS_V110 */
(()=>{'use strict';document.querySelectorAll('[data-payment-method-dialog-open]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-method-dialog-open');const dialog=id?document.getElementById(id):null;if(dialog instanceof HTMLDialogElement&&!dialog.open)dialog.showModal();});});document.querySelectorAll('[data-payment-method-dialog-close]').forEach(button=>{button.addEventListener('click',()=>{const dialog=button.closest('dialog');if(dialog instanceof HTMLDialogElement)dialog.close();});});document.querySelectorAll('.payment-method-dialog').forEach(dialog=>{dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});});document.querySelectorAll('[data-payment-method-toggle]').forEach(toggle=>{toggle.addEventListener('change',()=>{if(!(toggle instanceof HTMLInputElement)||toggle.disabled)return;const form=toggle.closest('form');const status=form?.querySelector('input[name="status"]');if(!(form instanceof HTMLFormElement)||!(status instanceof HTMLInputElement))return;status.value=toggle.checked?'active':'inactive';form.requestSubmit();});});document.querySelectorAll('[data-payment-secret-toggle]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-secret-toggle');const field=id?document.getElementById(id):null;if(!(field instanceof HTMLInputElement))return;const reveal=field.type==='password';field.type=reveal?'text':'password';button.textContent=reveal?'Hide':'Show';button.setAttribute('aria-pressed',reveal?'true':'false');});});})();

/* BARAMA_PAYMENT_METHODS_CONFIRMATION_COMPLETION_V116 */
/* BARAMA_PAYMENT_METHODS_CONFIRMATION_RESULT_V115
 *
 * Payment Methods confirmation completion bridge.
 *
 * Key corrections over v1.1.4:
 * 1. Replays the protected POST using application/x-www-form-urlencoded,
 *    matching the native HTML form submission instead of multipart FormData.
 * 2. Never hides 401/403/409/422 responses behind an automatic reload.
 * 3. Parses the controller JSON contract and reloads only when ok === true.
 * 4. Shows a premium persistent success/error alert with the real server result.
 *
 * The six-digit Change Confirmation server grant remains authoritative.
 */
(() => {
    'use strict';

    const PAYMENT_PATH = /^\/dashboard\/payment-methods\/\d+\/(?:configure|status)$/;
    const SAFE_REPLAY_ERROR =
        'This form cannot be verified safely. Refresh the page and try again.';
    const MAX_PENDING_AGE_MS = 120000;
    const MIN_REPLAY_DELAY_MS = 1600;
    const MAX_CONFIRM_WAIT_MS = 8000;
    const POLL_MS = 200;
    const FLASH_KEY = 'barama.payment-method.confirmation-result.v115';

    let pending = null;
    let replaying = false;
    let confirmStartedAt = 0;
    let confirmPoll = null;

    const installStyles = () => {
        if (document.getElementById('payment-method-result-v115-style')) return;
        const style = document.createElement('style');
        style.id = 'payment-method-result-v115-style';
        style.textContent = `
            .payment-method-result-v115 {
                position: fixed;
                top: 88px;
                right: 24px;
                z-index: 100000;
                width: min(420px, calc(100vw - 32px));
                padding: 14px 16px;
                border: 1px solid var(--border-color, rgba(148,163,184,.28));
                border-radius: 14px;
                background: var(--surface-raised, #142033);
                color: var(--text-primary, #f3f6fb);
                box-shadow: 0 18px 48px rgba(0,0,0,.28);
                display: grid;
                grid-template-columns: 22px 1fr auto;
                gap: 10px;
                align-items: start;
                font-size: 13px;
                line-height: 1.5;
            }
            .payment-method-result-v115[data-type="success"] {
                border-color: rgba(45,212,191,.45);
            }
            .payment-method-result-v115[data-type="error"] {
                border-color: rgba(248,113,113,.5);
            }
            .payment-method-result-v115__icon {
                font-size: 16px;
                line-height: 1.4;
            }
            .payment-method-result-v115__title {
                display: block;
                margin-bottom: 2px;
                font-weight: 750;
            }
            .payment-method-result-v115__message {
                color: var(--text-secondary, #aebbd0);
                overflow-wrap: anywhere;
            }
            .payment-method-result-v115__close {
                border: 0;
                background: transparent;
                color: inherit;
                cursor: pointer;
                font-size: 18px;
                line-height: 1;
                opacity: .75;
                padding: 0 2px;
            }
            @media (max-width: 640px) {
                .payment-method-result-v115 {
                    top: 72px;
                    right: 16px;
                    left: 16px;
                    width: auto;
                }
            }
        `;
        document.head.appendChild(style);
    };

    const alert = (type, message, persist = false) => {
        installStyles();
        document.querySelectorAll('.payment-method-result-v115').forEach((node) => node.remove());

        const box = document.createElement('div');
        box.className = 'payment-method-result-v115';
        box.dataset.type = type === 'success' ? 'success' : 'error';
        box.setAttribute('role', type === 'success' ? 'status' : 'alert');
        box.setAttribute('aria-live', 'polite');

        const icon = document.createElement('span');
        icon.className = 'payment-method-result-v115__icon';
        icon.textContent = type === 'success' ? '✓' : '!';

        const copy = document.createElement('div');
        const title = document.createElement('strong');
        title.className = 'payment-method-result-v115__title';
        title.textContent = type === 'success' ? 'Saved successfully' : 'Could not save changes';
        const body = document.createElement('div');
        body.className = 'payment-method-result-v115__message';
        body.textContent = message || (type === 'success' ? 'Payment method updated.' : 'The server rejected the change.');
        copy.append(title, body);

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'payment-method-result-v115__close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '×';
        close.addEventListener('click', () => box.remove());

        box.append(icon, copy, close);
        document.body.appendChild(box);

        if (persist) {
            try {
                sessionStorage.setItem(FLASH_KEY, JSON.stringify({ type, message }));
            } catch (_) {}
        }

        if (type === 'success') {
            window.setTimeout(() => box.remove(), 5000);
        }
    };

    const restoreFlash = () => {
        try {
            const raw = sessionStorage.getItem(FLASH_KEY);
            if (!raw) return;
            sessionStorage.removeItem(FLASH_KEY);
            const data = JSON.parse(raw);
            if (data && (data.type === 'success' || data.type === 'error')) {
                alert(data.type, String(data.message || ''), false);
            }
        } catch (_) {}
    };

    const normalizeAction = (form) => {
        try {
            return new URL(form.getAttribute('action') || window.location.href, window.location.href);
        } catch (_) {
            return null;
        }
    };

    const isPaymentForm = (form) => {
        if (!(form instanceof HTMLFormElement)) return false;
        const action = normalizeAction(form);
        if (!action || action.origin !== window.location.origin) return false;
        if (!PAYMENT_PATH.test(action.pathname)) return false;
        if ((form.method || 'get').toLowerCase() !== 'post') return false;
        const csrf = form.querySelector('input[name="_token"]');
        return form.hasAttribute('data-workspace-form')
            && csrf instanceof HTMLInputElement
            && csrf.value.trim() !== '';
    };

    const formEntries = (form, submitter = null) => {
        const data = new FormData(form);
        if (
            (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement)
            && submitter.name
            && !data.has(submitter.name)
        ) {
            data.append(submitter.name, submitter.value || '');
        }

        const entries = [];
        data.forEach((value, key) => {
            if (value instanceof File) {
                if (value.name) throw new Error('Payment Method file fields are not supported by confirmation replay.');
                return;
            }
            entries.push([key, String(value)]);
        });
        return entries;
    };

    const capture = (form, submitter = null) => {
        if (!isPaymentForm(form)) return;
        const action = normalizeAction(form);
        if (!action) return;

        try {
            pending = {
                form,
                action: action.toString(),
                path: action.pathname,
                entries: formEntries(form, submitter),
                capturedAt: Date.now(),
                attempts: 0,
                kind: action.pathname.endsWith('/status') ? 'status' : 'configure',
            };
        } catch (error) {
            pending = null;
            alert('error', error instanceof Error ? error.message : 'Could not prepare this payment change.');
            return;
        }

        document.documentElement.setAttribute('data-payment-method-v115-pending', pending.path);
    };

    document.addEventListener('submit', (event) => {
        if (event.target instanceof HTMLFormElement) {
            capture(event.target, event.submitter || null);
        }
    }, true);

    const confirmationDialog = () => Array.from(
        document.querySelectorAll('dialog, [role="dialog"]')
    ).find((node) => {
        if (!(node instanceof HTMLElement)) return false;
        const rect = node.getBoundingClientRect();
        const style = getComputedStyle(node);
        return rect.width > 0
            && rect.height > 0
            && style.display !== 'none'
            && style.visibility !== 'hidden'
            && node.textContent?.includes('Confirm this change');
    }) || null;

    const confirmButton = (target) => {
        if (!(target instanceof Element)) return null;
        const button = target.closest('button, input[type="submit"]');
        if (!(button instanceof HTMLElement)) return null;
        const label = (button instanceof HTMLInputElement ? button.value : button.textContent)
            ?.replace(/\s+/g, ' ').trim();
        if (label !== 'Confirm change') return null;
        const dialog = button.closest('dialog, [role="dialog"]');
        return dialog?.textContent?.includes('Confirm this change') ? button : null;
    };

    const dialogError = () => {
        const dialog = confirmationDialog();
        if (!dialog) return '';

        const explicit = Array.from(dialog.querySelectorAll(
            '[role="alert"], .error, .errors, .field-error, .form-error, [data-error], [class*="error"]'
        ))
            .filter((node) => node instanceof HTMLElement && node.offsetParent !== null)
            .map((node) => node.textContent?.trim() || '')
            .filter(Boolean)
            .join(' ');

        if (explicit) return explicit;
        if (dialog.textContent?.includes(SAFE_REPLAY_ERROR)) return SAFE_REPLAY_ERROR;
        return '';
    };

    const closeConfirmation = () => {
        const dialog = confirmationDialog();
        if (dialog instanceof HTMLDialogElement && dialog.open) {
            try { dialog.close(); return; } catch (_) {}
        }
        if (dialog instanceof HTMLElement) {
            dialog.setAttribute('hidden', '');
            dialog.setAttribute('aria-hidden', 'true');
        }
    };

    const bodyFromEntries = (entries) => {
        const params = new URLSearchParams();
        entries.forEach(([key, value]) => params.append(key, value));
        return params;
    };

    const parseResult = async (response) => {
        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        let json = null;
        let text = '';

        if (contentType.includes('application/json')) {
            try { json = await response.clone().json(); } catch (_) {}
        }

        if (!json) {
            try { text = (await response.clone().text()).replace(/\s+/g, ' ').trim(); } catch (_) {}
        }

        const message = json && typeof json === 'object'
            ? String(json.message || json.data?.message || json.error || '').trim()
            : (text.length <= 600 ? text : '');

        return {
            httpOk: response.ok,
            appOk: json && typeof json === 'object' ? json.ok === true : response.ok,
            message,
            json,
        };
    };

    const replay = async (reason) => {
        if (replaying || !pending || pending.attempts >= 1) return;

        if (Date.now() - pending.capturedAt > MAX_PENDING_AGE_MS) {
            alert('error', 'The confirmation expired. Save the payment method again.');
            pending = null;
            return;
        }

        if (!pending.form?.isConnected) {
            alert('error', 'The payment form changed before confirmation. Refresh and try again.');
            return;
        }

        const current = normalizeAction(pending.form);
        if (!current || current.origin !== location.origin || current.pathname !== pending.path) {
            alert('error', 'The payment form identity changed before confirmation.');
            return;
        }

        pending.attempts += 1;
        replaying = true;
        document.documentElement.setAttribute('data-payment-method-v115-replay', reason);

        try {
            const response = await fetch(pending.action, {
                method: 'POST',
                body: bodyFromEntries(pending.entries),
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'follow',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'Accept': 'application/json',
                },
            });

            const result = await parseResult(response);

            if (result.httpOk && result.appOk) {
                const fallback = pending.kind === 'status'
                    ? 'Payment method status updated.'
                    : 'Payment method configuration saved.';
                const message = result.message || fallback;

                document.documentElement.setAttribute('data-payment-method-v115-result', 'success');
                closeConfirmation();

                document.querySelectorAll('.payment-method-dialog').forEach((dialog) => {
                    if (dialog instanceof HTMLDialogElement && dialog.open) {
                        try { dialog.close(); } catch (_) {}
                    }
                });

                alert('success', message, true);
                window.setTimeout(() => window.location.reload(), 350);
                return;
            }

            const statusPrefix = response.status ? `HTTP ${response.status}: ` : '';
            const message = result.message
                || `${statusPrefix}The server rejected this payment method change.`;

            document.documentElement.setAttribute(
                'data-payment-method-v115-result',
                `error-${response.status || 'unknown'}`
            );

            // Never auto-reload rejected writes. Keep the real server result visible.
            alert('error', message);

            const dialog = confirmationDialog();
            if (dialog) {
                let inline = dialog.querySelector('[data-payment-method-v115-inline-error]');
                if (!(inline instanceof HTMLElement)) {
                    inline = document.createElement('p');
                    inline.setAttribute('data-payment-method-v115-inline-error', '1');
                    inline.setAttribute('role', 'alert');
                    inline.style.marginTop = '10px';
                    inline.style.fontSize = '13px';
                    inline.style.lineHeight = '1.45';
                    inline.style.color = 'var(--danger, #ef6b73)';
                    const form = dialog.querySelector('form') || dialog;
                    form.appendChild(inline);
                }
                inline.textContent = message;
            }
        } catch (error) {
            const message = error instanceof Error
                ? `Network/request error: ${error.message}`
                : 'The verified payment change could not be submitted.';
            alert('error', message);
            document.documentElement.setAttribute('data-payment-method-v115-result', 'network-error');
        } finally {
            replaying = false;
        }
    };

    const stopPoll = () => {
        if (confirmPoll !== null) {
            clearInterval(confirmPoll);
            confirmPoll = null;
        }
    };

    const beginConfirmWatch = (button) => {
        if (!pending || replaying) return;
        stopPoll();
        confirmStartedAt = Date.now();

        confirmPoll = window.setInterval(() => {
            if (!pending || replaying) {
                stopPoll();
                return;
            }

            const dialog = confirmationDialog();
            if (!dialog) {
                stopPoll();

                // The global Change Confirmation handler has accepted the
                // passcode and closed its dialog. For Payment Methods this is
                // the point at which the short-lived single-use approval grant
                // is available. v1.1.5 incorrectly stopped here and never sent
                // the pending configure/status POST, leaving the setup dialog
                // open with no persisted change.
                window.setTimeout(() => {
                    if (!pending || replaying || pending.attempts >= 1) return;
                    replay('confirmed-dialog-closed');
                }, 180);

                return;
            }

            const error = dialogError();
            if (error && error !== SAFE_REPLAY_ERROR) {
                stopPoll();
                return; // real invalid/expired passcode result stays visible
            }

            if (error === SAFE_REPLAY_ERROR) {
                stopPoll();
                replay('safe-replay-error');
                return;
            }

            const elapsed = Date.now() - confirmStartedAt;
            const busy = button instanceof HTMLButtonElement
                && (button.disabled || button.getAttribute('aria-busy') === 'true');

            if (elapsed >= MAX_CONFIRM_WAIT_MS || (elapsed >= MIN_REPLAY_DELAY_MS && !busy)) {
                stopPoll();
                replay('stuck-confirm-dialog');
            }
        }, POLL_MS);
    };

    document.addEventListener('click', (event) => {
        if (!pending) return;
        const button = confirmButton(event.target);
        if (button) beginConfirmWatch(button);
    }, true);

    const observer = new MutationObserver(() => {
        if (!pending || replaying) return;
        if (dialogError() !== SAFE_REPLAY_ERROR) return;
        stopPoll();
        replay('safe-replay-error');
    });

    const start = () => {
        installStyles();
        restoreFlash();
        if (document.body) {
            observer.observe(document.body, {
                subtree: true,
                childList: true,
                characterData: true,
                attributes: true,
                attributeFilter: ['hidden', 'open', 'class', 'aria-hidden'],
            });
        }
        document.documentElement.setAttribute('data-payment-method-confirmation-result-v115', 'ready');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
