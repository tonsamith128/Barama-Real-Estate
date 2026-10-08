(() => {
    const root = document.querySelector('[data-email-template-builder][data-email-template-version="137"]');
    if (!root) return;

    const form = root.querySelector('[data-email-builder-form]');
    const subject = root.querySelector('[data-email-subject]');
    const html = root.querySelector('[data-email-html]');
    const text = root.querySelector('[data-email-text]');
    const sampleNode = root.querySelector('[data-email-template-samples]');
    const previewSubject = root.querySelector('[data-email-preview-subject]');
    const previewHtml = root.querySelector('[data-email-preview-html]');
    const previewText = root.querySelector('[data-email-preview-text]');
    const htmlCount = root.querySelector('[data-email-html-count]');
    const textCount = root.querySelector('[data-email-text-count]');
    let samples = {};
    let lastField = html || subject || text;

    try {
        samples = JSON.parse(sampleNode?.textContent || '{}');
    } catch (_) {
        samples = {};
    }

    const replaceVariables = (value) => String(value || '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key) => {
        const normalized = String(key || '').toLowerCase();
        return Object.prototype.hasOwnProperty.call(samples, normalized) ? String(samples[normalized]) : '';
    });

    const refresh = () => {
        if (previewSubject) previewSubject.textContent = replaceVariables(subject?.value || '');
        if (previewHtml) previewHtml.srcdoc = replaceVariables(html?.value || '');
        if (previewText) previewText.textContent = replaceVariables(text?.value || '');
        if (htmlCount) htmlCount.textContent = `${(html?.value || '').length.toLocaleString()} / 20,000`;
        if (textCount) textCount.textContent = `${(text?.value || '').length.toLocaleString()} / 10,000`;
    };

    [subject, html, text].forEach((field) => {
        field?.addEventListener('focus', () => { lastField = field; });
        field?.addEventListener('input', refresh);
    });

    root.querySelectorAll('[data-email-builder-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-email-builder-tab');
            root.querySelectorAll('[data-email-builder-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
            root.querySelectorAll('[data-email-builder-panel]').forEach((panel) => {
                panel.hidden = panel.getAttribute('data-email-builder-panel') !== target;
            });
        });
    });

    const insertAtCursor = (field, value) => {
        if (!field || field.readOnly || field.disabled) return;
        const start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
        const end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
        field.setRangeText(value, start, end, 'end');
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
    };

    root.querySelectorAll('[data-email-variable]').forEach((button) => {
        button.addEventListener('click', () => insertAtCursor(lastField, button.getAttribute('data-email-variable') || ''));
    });

    root.querySelectorAll('[data-email-insert-html]').forEach((button) => {
        button.addEventListener('click', () => insertAtCursor(html, button.getAttribute('data-email-insert-html') || ''));
    });

    root.querySelectorAll('[data-email-wrap]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!html || html.readOnly || html.disabled) return;
            const tag = button.getAttribute('data-email-wrap') || 'strong';
            const start = html.selectionStart || 0;
            const end = html.selectionEnd || start;
            const selected = html.value.slice(start, end) || 'Text';
            insertAtCursor(html, `<${tag}>${selected}</${tag}>`);
        });
    });

    root.querySelectorAll('[data-email-preview-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.getAttribute('data-email-preview-mode');
            root.querySelectorAll('[data-email-preview-mode]').forEach((item) => item.classList.toggle('is-active', item === button));
            root.querySelector('[data-email-preview-frame]')?.classList.toggle('is-mobile', mode === 'mobile');
        });
    });

    // Preserve the focused editor target when clicking toolbar controls.
    form?.querySelectorAll('input, textarea').forEach((field) => {
        field.addEventListener('focus', () => { lastField = field; });
    });

    refresh();
})();
