/* BARAMA_ADMIN_EMAIL_TEMPLATES_V136 */
(() => {
    'use strict';

    const manager = document.querySelector('[data-email-template-manager]');
    if (!manager) return;

    const sampleValues = {
        title: 'Viewing confirmed',
        message: 'Your property viewing has been confirmed for Friday at 10:30 AM.',
        action_url: 'https://example.com/account/viewings',
        recipient_name: 'Samith',
        app_name: 'Barama Real Estate',
        app_url: 'https://example.com'
    };

    let lastFocus = null;

    const substitute = (value) => String(value || '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (match, key) => {
        const normalized = String(key || '').toLowerCase();
        return Object.prototype.hasOwnProperty.call(sampleValues, normalized)
            ? sampleValues[normalized]
            : match;
    });

    const editorField = (modal, name) => modal.querySelector(`[data-email-template-editor] [name="${name}"]`);

    const renderPreview = (modal) => {
        const subject = substitute(editorField(modal, 'subject_template')?.value || '');
        const html = substitute(editorField(modal, 'html_template')?.value || '');
        const text = substitute(editorField(modal, 'text_template')?.value || '');
        const subjectTarget = modal.querySelector('[data-email-template-preview-subject]');
        const htmlTarget = modal.querySelector('[data-email-template-preview-html]');
        const textTarget = modal.querySelector('[data-email-template-preview-text]');

        if (subjectTarget) subjectTarget.textContent = subject || '—';
        if (textTarget) textTarget.textContent = text || '—';
        if (htmlTarget) {
            const csp = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'">';
            htmlTarget.srcdoc = `<!doctype html><html><head><meta charset="utf-8">${csp}<style>body{font:15px/1.55 -apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#1f2937;padding:22px;margin:0}a{color:#2563eb}</style></head><body>${html || '<p>No HTML content.</p>'}</body></html>`;
        }
    };

    const setTab = (modal, tab) => {
        modal.querySelectorAll('[data-email-template-tab]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.emailTemplateTab === tab);
        });
        modal.querySelectorAll('[data-email-template-view]').forEach((view) => {
            view.hidden = view.dataset.emailTemplateView !== tab;
        });
        if (tab === 'preview') renderPreview(modal);
    };

    const closeModal = (modal) => {
        modal.hidden = true;
        document.body.classList.remove('email-template-modal-open');
        const openerId = modal.dataset.openerId;
        if (openerId) document.getElementById(openerId)?.focus();
    };

    const openModal = (modal, opener) => {
        if (!opener.id) opener.id = `email-template-opener-${Math.random().toString(36).slice(2)}`;
        modal.dataset.openerId = opener.id;
        modal.hidden = false;
        document.body.classList.add('email-template-modal-open');
        setTab(modal, 'content');
        window.requestAnimationFrame(() => modal.querySelector('[data-email-template-close]')?.focus());
    };

    manager.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-email-template-open]');
        if (opener) {
            const modal = document.getElementById(opener.dataset.emailTemplateOpen || '');
            if (modal) openModal(modal, opener);
            return;
        }

        const closer = event.target.closest('[data-email-template-close]');
        if (closer) {
            const modal = closer.closest('[data-email-template-modal]');
            if (modal) closeModal(modal);
            return;
        }

        const tabButton = event.target.closest('[data-email-template-tab]');
        if (tabButton) {
            const modal = tabButton.closest('[data-email-template-modal]');
            if (modal) setTab(modal, tabButton.dataset.emailTemplateTab || 'content');
            return;
        }

        const tokenButton = event.target.closest('[data-email-template-token]');
        if (tokenButton && !tokenButton.disabled) {
            const modal = tokenButton.closest('[data-email-template-modal]');
            const target = lastFocus && modal?.contains(lastFocus) ? lastFocus : editorField(modal, 'subject_template');
            if (!target || target.readOnly || target.disabled) return;
            const token = tokenButton.dataset.emailTemplateToken || '';
            const start = Number.isInteger(target.selectionStart) ? target.selectionStart : target.value.length;
            const end = Number.isInteger(target.selectionEnd) ? target.selectionEnd : start;
            target.value = target.value.slice(0, start) + token + target.value.slice(end);
            target.focus();
            target.setSelectionRange?.(start + token.length, start + token.length);
            target.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    manager.addEventListener('focusin', (event) => {
        const field = event.target.closest('[data-email-template-editor] input, [data-email-template-editor] textarea');
        if (field && !field.readOnly && !field.disabled) lastFocus = field;
    });

    manager.addEventListener('input', (event) => {
        const modal = event.target.closest('[data-email-template-modal]');
        if (!modal) return;
        const preview = modal.querySelector('[data-email-template-view="preview"]');
        if (preview && !preview.hidden) renderPreview(modal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const modal = manager.querySelector('[data-email-template-modal]:not([hidden])');
        if (modal) closeModal(modal);
    });

    manager.querySelectorAll('[data-email-template-modal]').forEach((modal) => {
        modal.addEventListener('mousedown', (event) => {
            if (event.target === modal) closeModal(modal);
        });
    });
})();
