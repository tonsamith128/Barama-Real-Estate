(() => {
    const modal = document.querySelector('[data-invoice-template-modal]');
    if (!modal) return;

    const form = document.querySelector('[data-invoice-template-form]');
    const previewShell = modal.querySelector('.invoice-template-preview-shell');
    const preview = modal.querySelector('[data-invoice-template-preview]');
    let dirty = false;

    const open = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
    };
    const close = (force = false) => {
        if (!force && dirty && !window.confirm('Discard unsaved invoice template changes?')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
        dirty = false;
    };

    document.querySelectorAll('[data-invoice-template-open]').forEach((button) => button.addEventListener('click', open));
    modal.querySelectorAll('[data-invoice-template-close]').forEach((button) => button.addEventListener('click', () => close()));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('is-open')) close(); });

    modal.querySelectorAll('[data-template-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.dataset.templateTab;
            modal.querySelectorAll('[data-template-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
            modal.querySelectorAll('[data-template-pane]').forEach((pane) => pane.classList.toggle('is-active', pane.dataset.templatePane === key));
        });
    });

    const updatePreview = (source) => {
        const key = source.dataset.previewSource;
        if (!key) return;
        const value = source.value;
        if (key === 'accent_color') {
            previewShell?.style.setProperty('--invoice-accent', value);
            const output = modal.querySelector('[data-accent-value]');
            if (output) output.textContent = value.toUpperCase();
            return;
        }
        if (key === 'logo_url') {
            const img = modal.querySelector('[data-preview-logo]');
            if (img) { img.src = value; img.hidden = value.trim() === ''; }
            return;
        }
        if (key === 'invoice_prefix') {
            const number = modal.querySelector('[data-preview-invoice-number]');
            if (number) number.textContent = `${value || 'INV'}-2026-0001`;
        }
        modal.querySelectorAll(`[data-preview-target="${CSS.escape(key)}"]`).forEach((target) => { target.textContent = value; });
    };

    form?.querySelectorAll('[data-preview-source]').forEach((source) => source.addEventListener('input', () => { dirty = true; updatePreview(source); }));
    form?.querySelectorAll('input, textarea, select').forEach((field) => field.addEventListener('change', () => { dirty = true; }));
    form?.addEventListener('submit', () => { dirty = false; });

    modal.querySelectorAll('[data-template-token]').forEach((button) => {
        button.addEventListener('click', async () => {
            const token = button.dataset.templateToken || '';
            try { await navigator.clipboard.writeText(token); }
            catch (_) { window.prompt('Copy variable', token); }
        });
    });

    document.querySelector('[data-template-reset]')?.addEventListener('click', (event) => {
        if (!window.confirm('Reset the invoice template to Barama defaults?')) event.preventDefault();
    });

    modal.querySelector('[data-template-print]')?.addEventListener('click', () => {
        if (!preview) return;
        const win = window.open('', '_blank', 'noopener,noreferrer,width=1000,height=800');
        if (!win) return;
        win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Invoice Template Preview</title><style>body{margin:0;padding:32px;background:#eef2f7;font-family:Inter,system-ui,sans-serif}.invoice-template-preview{margin:0 auto}@media print{body{padding:0;background:#fff}.invoice-template-preview{box-shadow:none;max-width:none;width:auto}}</style></head><body>${preview.outerHTML}</body></html>`);
        win.document.close();
        win.focus();
        window.setTimeout(() => win.print(), 250);
    });

    window.setTimeout(() => document.querySelectorAll('[data-invoice-template-toast]').forEach((toast) => toast.remove()), 5000);
    const params = new URLSearchParams(window.location.search);
    if (params.has('invoice_template')) { open(); window.history.replaceState({}, '', window.location.pathname); }
})();
