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
