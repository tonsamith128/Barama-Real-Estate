/* BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_V111
 *
 * DOM-stability bridge for Barama's existing Change Confirmation security layer.
 * Payment method/provider dialogs were emitted from table bodies. Browsers may
 * foster-parent invalid table children, so security replay must not depend on
 * that parser-dependent location.
 *
 * This bridge does NOT bypass passcode verification and does not alter the
 * approval grant. It only stabilizes the protected form DOM before submission.
 */
(() => {
    'use strict';

    const safeIdPart = (value) => String(value || 'dialog')
        .replace(/[^A-Za-z0-9_-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 96) || 'dialog';

    const stabilizeDialogForms = () => {
        const dialogs = document.querySelectorAll(
            '.payment-method-dialog, .payment-provider-dialog'
        );

        dialogs.forEach((dialog, dialogIndex) => {
            if (!(dialog instanceof HTMLDialogElement)) {
                return;
            }

            if (!dialog.id) {
                dialog.id = `barama-secure-dialog-${dialogIndex + 1}`;
            }

            dialog.querySelectorAll('form').forEach((form, formIndex) => {
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                if (!form.id) {
                    form.id = [
                        'barama-secure-form',
                        safeIdPart(dialog.id),
                        String(formIndex + 1),
                    ].join('-');
                }

                form.setAttribute('data-change-confirmation-stable-form', '1');
                form.setAttribute(
                    'data-change-confirmation-stable-id',
                    form.id
                );
            });

            // Moving a connected node preserves its event listeners and form values.
            // Top-level body placement removes parser-dependent table foster parenting.
            if (dialog.parentElement !== document.body) {
                document.body.appendChild(dialog);
            }
        });

        document.documentElement.setAttribute(
            'data-payment-dialogs-stabilized',
            '1'
        );
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            stabilizeDialogForms,
            { once: true }
        );
    } else {
        stabilizeDialogForms();
    }
})();


/* BARAMA_PAYMENT_METHODS_V110 */
/* v211 cache-busted compatibility payload */
(()=>{'use strict';document.querySelectorAll('[data-payment-method-dialog-open]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-method-dialog-open');const dialog=id?document.getElementById(id):null;if(dialog instanceof HTMLDialogElement&&!dialog.open)dialog.showModal();});});document.querySelectorAll('[data-payment-method-dialog-close]').forEach(button=>{button.addEventListener('click',()=>{const dialog=button.closest('dialog');if(dialog instanceof HTMLDialogElement)dialog.close();});});document.querySelectorAll('.payment-method-dialog').forEach(dialog=>{dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});});document.querySelectorAll('[data-payment-method-toggle]').forEach(toggle=>{toggle.addEventListener('change',()=>{if(!(toggle instanceof HTMLInputElement)||toggle.disabled)return;const form=toggle.closest('form');const status=form?.querySelector('input[name="status"]');if(!(form instanceof HTMLFormElement)||!(status instanceof HTMLInputElement))return;status.value=toggle.checked?'active':'inactive';form.requestSubmit();});});document.querySelectorAll('[data-payment-secret-toggle]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-payment-secret-toggle');const field=id?document.getElementById(id):null;if(!(field instanceof HTMLInputElement))return;const reveal=field.type==='password';field.type=reveal?'text':'password';button.textContent=reveal?'Hide':'Show';button.setAttribute('aria-pressed',reveal?'true':'false');});});})();
