/* BARAMA_ADMIN_SECURITY_FACTORS_V132 */
(() => {
    'use strict';
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-security-copy]');
        if (!button) return;
        const key = button.getAttribute('data-security-copy');
        const source = document.querySelector(`[data-security-copy-source="${CSS.escape(key || '')}"]`);
        if (!source) return;
        const value = (source.textContent || '').trim();
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
            const original = button.textContent;
            button.textContent = 'Copied';
            window.setTimeout(() => { button.textContent = original; }, 1200);
        } catch (error) {
            window.getSelection()?.selectAllChildren(source);
        }
    });
})();
