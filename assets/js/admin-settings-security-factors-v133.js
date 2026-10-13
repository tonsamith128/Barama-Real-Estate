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
