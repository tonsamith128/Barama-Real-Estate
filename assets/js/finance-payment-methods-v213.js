/* BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_V112 */
/* BARAMA_PAYMENT_METHODS_V110 */
(()=>{'use strict';document.querySelectorAll('[data-payment-method-dialog-open]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-method-dialog-open');const dialog=id?document.getElementById(id):null;if(dialog instanceof HTMLDialogElement&&!dialog.open)dialog.showModal();});});document.querySelectorAll('[data-payment-method-dialog-close]').forEach(button=>{button.addEventListener('click',()=>{const dialog=button.closest('dialog');if(dialog instanceof HTMLDialogElement)dialog.close();});});document.querySelectorAll('.payment-method-dialog').forEach(dialog=>{dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});});document.querySelectorAll('[data-payment-method-toggle]').forEach(toggle=>{toggle.addEventListener('change',()=>{if(!(toggle instanceof HTMLInputElement)||toggle.disabled)return;const form=toggle.closest('form');const status=form?.querySelector('input[name="status"]');if(!(form instanceof HTMLFormElement)||!(status instanceof HTMLInputElement))return;status.value=toggle.checked?'active':'inactive';form.requestSubmit();});});document.querySelectorAll('[data-payment-secret-toggle]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-secret-toggle');const field=id?document.getElementById(id):null;if(!(field instanceof HTMLInputElement))return;const reveal=field.type==='password';field.type=reveal?'text':'password';button.textContent=reveal?'Hide':'Show';button.setAttribute('aria-pressed',reveal?'true':'false');});});})();



/* BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_REPLAY_V113
 *
 * Payment-Methods-only recovery for Barama's existing 6-digit Change
 * Confirmation flow.
 *
 * The global Change Confirmation layer remains authoritative. This bridge does
 * not create or bypass an approval grant. It retries the exact original
 * Payment Method POST only after the existing confirmation UI emits its exact
 * safe-replay failure text. The server still rejects the retry unless the
 * existing short-lived single-use approval grant is valid.
 */
(() => {
    'use strict';

    const SAFE_ERROR =
        'This form cannot be verified safely. Refresh the page and try again.';
    const PAYMENT_PATH = /^\/dashboard\/payment-methods\/\d+\/(?:configure|status)$/;
    const MAX_PENDING_AGE_MS = 120000;

    let pending = null;
    let replaying = false;
    let observer = null;

    const normalizeAction = (form) => {
        try {
            return new URL(form.action || window.location.href, window.location.href);
        } catch (_) {
            return null;
        }
    };

    const captureEntries = (form) => {
        const entries = [];
        const data = new FormData(form);

        data.forEach((value, key) => {
            // Payment Method forms currently contain no file uploads. If one is
            // added later, preserve the File object in memory without reading it.
            entries.push([key, value]);
        });

        return entries;
    };

    const buildBody = (entries) => {
        const body = new FormData();
        entries.forEach(([key, value]) => body.append(key, value));
        return body;
    };

    const isSafePaymentForm = (form) => {
        if (!(form instanceof HTMLFormElement)) return false;

        const action = normalizeAction(form);
        if (!action) return false;

        if (action.origin !== window.location.origin) return false;
        if (!PAYMENT_PATH.test(action.pathname)) return false;
        if ((form.method || 'get').toLowerCase() !== 'post') return false;

        const csrf = form.querySelector('input[name="_token"]');
        if (!(csrf instanceof HTMLInputElement) || csrf.value.trim() === '') {
            return false;
        }

        return form.hasAttribute('data-workspace-form');
    };

    const remember = (form) => {
        if (!isSafePaymentForm(form)) return;

        const action = normalizeAction(form);
        if (!action) return;

        pending = {
            form,
            action: action.toString(),
            path: action.pathname,
            entries: captureEntries(form),
            capturedAt: Date.now(),
            attempts: 0,
        };

        document.documentElement.setAttribute(
            'data-payment-method-confirmation-pending',
            pending.path
        );
    };

    // Capture before the global Change Confirmation listener can prevent the
    // submission. This records only governed Payment Method writes.
    document.addEventListener(
        'submit',
        (event) => {
            if (!(event.target instanceof HTMLFormElement)) return;
            remember(event.target);
        },
        true
    );

    const exactSafeReplayErrorVisible = () => {
        if (!document.body) return false;
        return document.body.textContent?.includes(SAFE_ERROR) === true;
    };

    const showRecoveryError = (message) => {
        const modal = Array.from(
            document.querySelectorAll('dialog, [role="dialog"]')
        ).find((node) => node.textContent?.includes('Confirm this change'));

        if (!modal) {
            console.error('[Payment Methods confirmation recovery]', message);
            return;
        }

        let target = modal.querySelector(
            '[data-payment-confirmation-recovery-message]'
        );

        if (!(target instanceof HTMLElement)) {
            target = document.createElement('p');
            target.setAttribute(
                'data-payment-confirmation-recovery-message',
                '1'
            );
            target.style.marginTop = '10px';
            target.style.fontSize = '13px';
            target.style.lineHeight = '1.45';
            target.style.color = 'var(--danger, #ef6b73)';

            const footer =
                modal.querySelector('.modal-footer, [class*="footer"]')
                || modal.querySelector('form')
                || modal;

            footer.prepend(target);
        }

        target.textContent = message;
    };

    const responseMessage = async (response) => {
        const type = response.headers.get('content-type') || '';

        try {
            if (type.includes('application/json')) {
                const data = await response.json();

                if (data && typeof data === 'object') {
                    return String(
                        data.message
                        || data.error
                        || data.detail
                        || ''
                    ).trim();
                }
            } else {
                const text = (await response.text()).trim();
                if (text && text.length <= 400) return text;
            }
        } catch (_) {
            // Response body is optional for this recovery path.
        }

        return '';
    };

    const replayPendingPaymentMethod = async () => {
        if (replaying || !pending) return;

        if (Date.now() - pending.capturedAt > MAX_PENDING_AGE_MS) {
            pending = null;
            showRecoveryError(
                'The confirmation expired. Save the payment method again.'
            );
            return;
        }

        if (pending.attempts >= 1) return;
        if (!pending.form?.isConnected) {
            showRecoveryError(
                'The payment form changed before confirmation. Refresh and try again.'
            );
            return;
        }

        // Re-check the live form identity before consuming the single-use grant.
        const current = normalizeAction(pending.form);
        if (
            !current
            || current.origin !== window.location.origin
            || current.pathname !== pending.path
            || !PAYMENT_PATH.test(current.pathname)
        ) {
            showRecoveryError(
                'The payment form identity changed before confirmation.'
            );
            return;
        }

        pending.attempts += 1;
        replaying = true;

        document.documentElement.setAttribute(
            'data-payment-method-confirmation-replay',
            'running'
        );

        try {
            const response = await window.fetch(pending.action, {
                method: 'POST',
                body: buildBody(pending.entries),
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'follow',
                headers: {
                    'Accept': 'application/json, text/plain, */*',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Barama-Payment-Confirmation-Replay': '1',
                },
            });

            if (!response.ok) {
                const detail = await responseMessage(response);

                document.documentElement.setAttribute(
                    'data-payment-method-confirmation-replay',
                    'rejected'
                );

                showRecoveryError(
                    detail
                    || `The verified payment change was rejected (${response.status}).`
                );
                return;
            }

            document.documentElement.setAttribute(
                'data-payment-method-confirmation-replay',
                'accepted'
            );

            // Successful POST consumes the existing authorization grant. Reload
            // from the canonical server state so Configured/Enabled is accurate.
            window.location.reload();
        } catch (error) {
            document.documentElement.setAttribute(
                'data-payment-method-confirmation-replay',
                'failed'
            );

            showRecoveryError(
                'The verified payment change could not be submitted. Try again.'
            );

            console.error(
                '[Payment Methods confirmation recovery]',
                error
            );
        } finally {
            replaying = false;
        }
    };

    const inspect = () => {
        if (!pending || replaying) return;
        if (!exactSafeReplayErrorVisible()) return;

        // Let the global confirmation handler finish its own promise chain and
        // persist the approval session before we consume the grant.
        window.setTimeout(replayPendingPaymentMethod, 120);
    };

    const startObserver = () => {
        if (!document.body || observer) return;

        observer = new MutationObserver(inspect);
        observer.observe(document.body, {
            subtree: true,
            childList: true,
            characterData: true,
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserver, {
            once: true,
        });
    } else {
        startObserver();
    }

    document.documentElement.setAttribute(
        'data-payment-method-confirmation-recovery-v113',
        'ready'
    );
})();
