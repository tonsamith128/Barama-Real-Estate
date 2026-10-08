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

    const close = () => {
        pendingForm = null;
        pendingSubmitter = null;
        clearCode();
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
        pendingSubmitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        clearCode();
        setError('');
        dialog.showModal();
        window.setTimeout(() => digits[0].focus(), 40);
    }, true);

    cancelButtons.forEach((button) => button.addEventListener('click', close));

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
})();
