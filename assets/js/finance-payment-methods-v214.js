/* BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_V112 */
/* BARAMA_PAYMENT_METHODS_V110 */
(()=>{'use strict';document.querySelectorAll('[data-payment-method-dialog-open]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-method-dialog-open');const dialog=id?document.getElementById(id):null;if(dialog instanceof HTMLDialogElement&&!dialog.open)dialog.showModal();});});document.querySelectorAll('[data-payment-method-dialog-close]').forEach(button=>{button.addEventListener('click',()=>{const dialog=button.closest('dialog');if(dialog instanceof HTMLDialogElement)dialog.close();});});document.querySelectorAll('.payment-method-dialog').forEach(dialog=>{dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});});document.querySelectorAll('[data-payment-method-toggle]').forEach(toggle=>{toggle.addEventListener('change',()=>{if(!(toggle instanceof HTMLInputElement)||toggle.disabled)return;const form=toggle.closest('form');const status=form?.querySelector('input[name="status"]');if(!(form instanceof HTMLFormElement)||!(status instanceof HTMLInputElement))return;status.value=toggle.checked?'active':'inactive';form.requestSubmit();});});document.querySelectorAll('[data-payment-secret-toggle]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-secret-toggle');const field=id?document.getElementById(id):null;if(!(field instanceof HTMLInputElement))return;const reveal=field.type==='password';field.type=reveal?'text':'password';button.textContent=reveal?'Hide':'Show';button.setAttribute('aria-pressed',reveal?'true':'false');});});})();



/* BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_WATCHDOG_V114
 *
 * Payment-Methods-only completion watchdog for Barama's existing six-digit
 * Change Confirmation security layer.
 *
 * Security boundary:
 * - This code does NOT mint, validate, extend, or bypass a confirmation grant.
 * - It does NOT modify change-confirmation-v133.js or server middleware.
 * - It replays only the exact captured same-origin Payment Method POST.
 * - The server-side confirmation middleware remains authoritative: without the
 *   valid short-lived single-use grant, the replay is rejected.
 */
(() => {
    'use strict';

    const PAYMENT_PATH =
        /^\/dashboard\/payment-methods\/\d+\/(?:configure|status)$/;

    const SAFE_REPLAY_ERROR =
        'This form cannot be verified safely. Refresh the page and try again.';

    const WATCHDOG_DELAY_MS = 900;
    const MAX_PENDING_AGE_MS = 120000;

    let pending = null;
    let replaying = false;
    let watchdogTimer = null;

    const normalizeAction = (form) => {
        try {
            return new URL(
                form.getAttribute('action') || window.location.href,
                window.location.href
            );
        } catch (_) {
            return null;
        }
    };

    const isGovernedPaymentForm = (form) => {
        if (!(form instanceof HTMLFormElement)) return false;

        const action = normalizeAction(form);
        if (!action) return false;

        if (action.origin !== window.location.origin) return false;
        if (!PAYMENT_PATH.test(action.pathname)) return false;
        if ((form.method || 'get').toLowerCase() !== 'post') return false;

        const csrf = form.querySelector('input[name="_token"]');

        return (
            form.hasAttribute('data-workspace-form')
            && csrf instanceof HTMLInputElement
            && csrf.value.trim() !== ''
        );
    };

    const captureForm = (form, submitter = null) => {
        if (!isGovernedPaymentForm(form)) return;

        const action = normalizeAction(form);
        if (!action) return;

        const data = new FormData(form);

        if (
            submitter instanceof HTMLButtonElement
            || submitter instanceof HTMLInputElement
        ) {
            if (submitter.name && !data.has(submitter.name)) {
                data.append(submitter.name, submitter.value || '');
            }
        }

        const entries = [];
        data.forEach((value, key) => entries.push([key, value]));

        pending = {
            form,
            action: action.toString(),
            path: action.pathname,
            entries,
            capturedAt: Date.now(),
            attempts: 0,
        };

        document.documentElement.setAttribute(
            'data-payment-method-confirmation-pending',
            pending.path
        );
    };

    // Capture before the global confirmation listener prevents the native POST.
    document.addEventListener(
        'submit',
        (event) => {
            if (!(event.target instanceof HTMLFormElement)) return;
            captureForm(event.target, event.submitter || null);
        },
        true
    );

    const buildBody = (entries) => {
        const body = new FormData();
        entries.forEach(([key, value]) => body.append(key, value));
        return body;
    };

    const visible = (element) => {
        if (!(element instanceof HTMLElement)) return false;
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();

        return (
            style.display !== 'none'
            && style.visibility !== 'hidden'
            && Number(style.opacity || '1') !== 0
            && rect.width > 0
            && rect.height > 0
        );
    };

    const confirmationDialog = () => {
        const candidates = Array.from(
            document.querySelectorAll('dialog, [role="dialog"]')
        );

        return candidates.find((node) => {
            if (!(node instanceof HTMLElement) || !visible(node)) return false;
            return node.textContent?.includes('Confirm this change') === true;
        }) || null;
    };

    const confirmButton = (target) => {
        if (!(target instanceof Element)) return null;

        const button = target.closest('button, input[type="submit"]');
        if (!(button instanceof HTMLElement)) return null;

        const label = (
            button instanceof HTMLInputElement
                ? button.value
                : button.textContent
        )?.replace(/\s+/g, ' ').trim();

        if (label !== 'Confirm change') return null;

        const dialog = button.closest('dialog, [role="dialog"]');
        if (!(dialog instanceof HTMLElement)) return null;
        if (!dialog.textContent?.includes('Confirm this change')) return null;

        return button;
    };

    const dialogErrorText = () => {
        const dialog = confirmationDialog();
        if (!dialog) return '';

        const explicit = Array.from(
            dialog.querySelectorAll(
                '[role="alert"], .error, .errors, .field-error, '
                + '.form-error, [data-error], [class*="error"]'
            )
        )
            .filter((node) => visible(node))
            .map((node) => node.textContent?.trim() || '')
            .filter(Boolean)
            .join(' ');

        if (explicit) return explicit;

        const text = dialog.textContent || '';

        if (text.includes(SAFE_REPLAY_ERROR)) {
            return SAFE_REPLAY_ERROR;
        }

        // Known failure-language fallback. Do not treat static explanatory copy
        // such as "expires quickly" as an error.
        const match = text.match(
            /\b(?:incorrect|invalid|wrong|failed|cannot|rejected|unauthori[sz]ed)\b[^.]*\.?/i
        );

        return match ? match[0].trim() : '';
    };

    const putRecoveryMessage = (message) => {
        const dialog = confirmationDialog();

        if (!dialog) {
            console.error('[Payment Methods confirmation watchdog]', message);
            return;
        }

        let target = dialog.querySelector(
            '[data-payment-confirmation-watchdog-message]'
        );

        if (!(target instanceof HTMLElement)) {
            target = document.createElement('p');
            target.setAttribute(
                'data-payment-confirmation-watchdog-message',
                '1'
            );
            target.setAttribute('role', 'alert');
            target.style.margin = '10px 0 0';
            target.style.fontSize = '13px';
            target.style.lineHeight = '1.45';
            target.style.color = 'var(--danger, #ef6b73)';

            const actions =
                dialog.querySelector(
                    '.modal-footer, .dialog-footer, [class*="actions"], '
                    + '[class*="footer"]'
                )
                || dialog.querySelector('form')
                || dialog;

            actions.prepend(target);
        }

        target.textContent = message;
    };

    const responseMessage = async (response) => {
        const contentType =
            (response.headers.get('content-type') || '').toLowerCase();

        try {
            if (contentType.includes('application/json')) {
                const body = await response.clone().json();

                if (body && typeof body === 'object') {
                    return String(
                        body.message
                        || body.error
                        || body.detail
                        || ''
                    ).trim();
                }
            }

            if (
                contentType.includes('text/plain')
                || contentType.includes('text/html')
            ) {
                const text = (await response.clone().text())
                    .replace(/\s+/g, ' ')
                    .trim();

                if (text && text.length <= 500) return text;
            }
        } catch (_) {
            // Response text is optional. HTTP status remains authoritative.
        }

        return '';
    };

    const closeConfirmationDialog = () => {
        const dialog = confirmationDialog();

        if (dialog instanceof HTMLDialogElement && dialog.open) {
            try {
                dialog.close();
                return;
            } catch (_) {
                // Continue to generic hide fallback.
            }
        }

        if (dialog instanceof HTMLElement) {
            dialog.setAttribute('hidden', '');
            dialog.setAttribute('aria-hidden', 'true');
        }
    };

    const replay = async (reason) => {
        if (replaying || !pending) return;

        if (Date.now() - pending.capturedAt > MAX_PENDING_AGE_MS) {
            pending = null;
            putRecoveryMessage(
                'The confirmation expired. Save the payment method again.'
            );
            return;
        }

        if (pending.attempts >= 1) return;

        if (!pending.form?.isConnected) {
            putRecoveryMessage(
                'The payment form is no longer available. Refresh and try again.'
            );
            return;
        }

        const current = normalizeAction(pending.form);

        if (
            !current
            || current.origin !== window.location.origin
            || current.pathname !== pending.path
            || !PAYMENT_PATH.test(current.pathname)
        ) {
            putRecoveryMessage(
                'The payment form identity changed. Refresh and try again.'
            );
            return;
        }

        const csrf = pending.entries.find(([key]) => key === '_token');

        if (!csrf || !String(csrf[1] || '').trim()) {
            putRecoveryMessage(
                'The payment form security token is missing. Refresh and try again.'
            );
            return;
        }

        pending.attempts += 1;
        replaying = true;

        document.documentElement.setAttribute(
            'data-payment-method-confirmation-watchdog',
            `replaying:${reason}`
        );

        try {
            const response = await window.fetch(pending.action, {
                method: 'POST',
                body: buildBody(pending.entries),
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'follow',
                headers: {
                    'Accept': 'application/json, text/plain, text/html, */*',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Barama-Payment-Confirmation-Watchdog': 'v114',
                },
            });

            if (response.ok) {
                document.documentElement.setAttribute(
                    'data-payment-method-confirmation-watchdog',
                    'accepted'
                );

                closeConfirmationDialog();

                // Reload from canonical server state. This closes both dialogs
                // and reflects Configured / Enabled from the persisted record.
                window.location.reload();
                return;
            }

            const message = await responseMessage(response);

            document.documentElement.setAttribute(
                'data-payment-method-confirmation-watchdog',
                `rejected:${response.status}`
            );

            // If the original global handler actually completed the save but
            // failed to close its UI, its single-use grant may already be
            // consumed, making this watchdog replay return 401/403/409.
            // When there is no visible passcode error, one canonical reload is
            // safe and reveals the real persisted state.
            if (!dialogErrorText() && [401, 403, 409].includes(response.status)) {
                closeConfirmationDialog();
                window.location.reload();
                return;
            }

            putRecoveryMessage(
                message
                || `The verified payment change was rejected (${response.status}).`
            );
        } catch (error) {
            document.documentElement.setAttribute(
                'data-payment-method-confirmation-watchdog',
                'network-failed'
            );

            putRecoveryMessage(
                'The verified payment change could not be submitted. Try again.'
            );

            console.error(
                '[Payment Methods confirmation watchdog]',
                error
            );
        } finally {
            replaying = false;
        }
    };

    const startWatchdog = () => {
        if (!pending || replaying) return;

        if (watchdogTimer !== null) {
            window.clearTimeout(watchdogTimer);
        }

        document.documentElement.setAttribute(
            'data-payment-method-confirmation-watchdog',
            'waiting'
        );

        watchdogTimer = window.setTimeout(() => {
            watchdogTimer = null;

            if (!pending || replaying) return;

            const error = dialogErrorText();

            // Keep real invalid/expired passcode errors visible. The exact
            // v133 safe-replay error is the known integration failure and is
            // recoverable through the governed POST below.
            if (error && error !== SAFE_REPLAY_ERROR) {
                document.documentElement.setAttribute(
                    'data-payment-method-confirmation-watchdog',
                    'passcode-error'
                );
                return;
            }

            // If the dialog already closed, the global handler completed.
            if (!confirmationDialog()) return;

            replay(
                error === SAFE_REPLAY_ERROR
                    ? 'safe-replay-error'
                    : 'stuck-confirm-dialog'
            );
        }, WATCHDOG_DELAY_MS);
    };

    // Watch the existing global Confirm button without replacing its handler.
    // Capture phase ensures the watchdog is armed even if v133 stops bubbling.
    document.addEventListener(
        'click',
        (event) => {
            if (!pending) return;
            if (!confirmButton(event.target)) return;
            startWatchdog();
        },
        true
    );

    // If v133 emits the old explicit safe-replay error before the timeout,
    // trigger recovery immediately instead of waiting the full delay.
    const observer = new MutationObserver(() => {
        if (!pending || replaying) return;
        if (dialogErrorText() !== SAFE_REPLAY_ERROR) return;

        if (watchdogTimer !== null) {
            window.clearTimeout(watchdogTimer);
            watchdogTimer = null;
        }

        replay('safe-replay-error');
    });

    const startObserver = () => {
        if (!document.body) return;

        observer.observe(document.body, {
            subtree: true,
            childList: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['hidden', 'open', 'class', 'aria-hidden'],
        });

        document.documentElement.setAttribute(
            'data-payment-method-confirmation-watchdog-v114',
            'ready'
        );
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserver, {
            once: true,
        });
    } else {
        startObserver();
    }
})();
