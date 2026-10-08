
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
