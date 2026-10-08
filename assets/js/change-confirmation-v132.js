/* BARAMA_CHANGE_CONFIRMATION_V132 */
(() => {
    'use strict';

    const body = document.body;
    if (!body || body.dataset.changeConfirmationRequired !== '1') return;

    const endpoint = body.dataset.changeConfirmationEndpoint || '';
    const dialog = document.querySelector('[data-change-confirmation-dialog]');
    const input = dialog?.querySelector('[data-change-confirmation-passcode]');
    const errorBox = dialog?.querySelector('[data-change-confirmation-error]');
    const approve = dialog?.querySelector('[data-change-confirmation-approve]');
    const cancelButtons = dialog?.querySelectorAll('[data-change-confirmation-cancel]') || [];
    if (!endpoint || !dialog || !input || !approve || !errorBox) return;

    let pendingForm = null;
    let pendingSubmitter = null;

    const setError = (message = '') => {
        errorBox.textContent = message;
        errorBox.hidden = message === '';
    };

    const close = () => {
        pendingForm = null;
        pendingSubmitter = null;
        input.value = '';
        setError('');
        if (dialog.open) dialog.close();
    };

    const exempt = (form) => {
        if (!(form instanceof HTMLFormElement)) return true;
        if (form.dataset.changeConfirmationExempt === '1') return true;
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') return true;
        const action = new URL(form.action || window.location.href, window.location.href);
        if (action.origin !== window.location.origin) return true;
        const route = action.pathname;
        if (!/(^|\/)(dashboard|developer|account)(\/|$)/.test(route)) return true;
        return false;
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
        pendingSubmitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        input.value = '';
        setError('');
        dialog.showModal();
        window.setTimeout(() => input.focus(), 40);
    }, true);

    cancelButtons.forEach((button) => button.addEventListener('click', close));

    approve.addEventListener('click', async () => {
        if (!pendingForm) return;
        const passcode = input.value.trim();
        if (!/^\d{6}$/.test(passcode)) {
            setError('Enter exactly 6 digits.');
            input.focus();
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
                input.select();
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
            close();
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

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            approve.click();
        }
        if (event.key === 'Escape') close();
    });
})();
