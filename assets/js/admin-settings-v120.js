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
