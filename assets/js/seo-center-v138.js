/* BARAMA_SEO_CENTER_PREMIUM_V138 */
(() => {
    'use strict';

    const inspector = document.querySelector('[data-seo-inspector]');
    if (!inspector) return;

    const title = inspector.querySelector('[data-seo-title]');
    const path = inspector.querySelector('[data-seo-path]');
    const score = inspector.querySelector('[data-seo-score]');
    const label = inspector.querySelector('[data-seo-label]');
    const checklist = inspector.querySelector('[data-seo-checklist]');
    const issues = inspector.querySelector('[data-seo-issues]');
    const serpPath = inspector.querySelector('[data-seo-serp-path]');
    const serpTitle = inspector.querySelector('[data-seo-serp-title]');
    const publicLink = inspector.querySelector('[data-seo-public]');
    const editLink = inspector.querySelector('[data-seo-edit]');
    const copy = inspector.querySelector('[data-seo-copy]');

    let current = null;
    let lastTrigger = null;

    const stateLabel = (value) => {
        if (!value || value === 'n/a') return 'Not applicable';
        return String(value)
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    };

    const goodState = (value) =>
        ['ready', 'indexable', 'n/a'].includes(String(value || ''));

    const checklistItem = (name, value) => {
        const row = document.createElement('div');
        row.className = `seo-inspector__check ${goodState(value) ? '' : 'is-attention'}`.trim();

        const dot = document.createElement('i');
        const text = document.createElement('div');
        const strong = document.createElement('strong');
        const small = document.createElement('small');

        strong.textContent = name;
        small.textContent = stateLabel(value);

        text.append(strong, small);
        row.append(dot, text);

        return row;
    };

    const render = (payload) => {
        current = payload;

        title.textContent = payload.title || 'SEO record';
        path.textContent = payload.path || '—';
        score.textContent = String(payload.score ?? 0);
        label.textContent = payload.label || '—';
        serpPath.textContent = payload.path || '/';
        serpTitle.textContent = payload.title || 'Untitled public record';

        publicLink.href = payload.path || '#';
        editLink.href = payload.edit_url || '/dashboard/seo';

        checklist.replaceChildren(
            checklistItem('Indexability', payload.index),
            checklistItem('Description', payload.description),
            checklistItem('Social image', payload.image),
            checklistItem('Image alt text', payload.alt),
        );

        issues.replaceChildren();

        const issueList = Array.isArray(payload.issues)
            ? payload.issues
            : [];

        if (!issueList.length) {
            const ready = document.createElement('span');
            ready.className = 'is-empty';
            ready.textContent = 'No priority coverage issue detected for this record.';
            issues.append(ready);
        } else {
            issueList.forEach((issue) => {
                const row = document.createElement('div');
                row.className = 'seo-inspector__issue';

                const text = document.createElement('div');
                const strong = document.createElement('strong');
                const small = document.createElement('small');

                strong.textContent = issue.label || 'SEO attention';
                small.textContent = issue.detail || '';

                text.append(strong, small);
                row.append(text);
                issues.append(row);
            });
        }
    };

    const show = (trigger, payload) => {
        lastTrigger = trigger;
        render(payload);
        inspector.hidden = false;
        inspector.setAttribute('aria-hidden', 'false');
        document.body.classList.add('seo-inspector-open');

        inspector
            .querySelector('.seo-inspector__close')
            ?.focus({preventScroll: true});
    };

    const hide = () => {
        inspector.hidden = true;
        inspector.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('seo-inspector-open');

        lastTrigger?.focus({preventScroll: true});
        lastTrigger = null;
        current = null;
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-seo-inspect]');

        if (trigger) {
            event.preventDefault();

            try {
                const payload = JSON.parse(
                    trigger.getAttribute('data-seo-inspect') || '{}'
                );
                show(trigger, payload);
            } catch (_) {
                return;
            }
        }

        if (event.target.closest('[data-seo-close]')) {
            hide();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !inspector.hidden) {
            hide();
        }
    });

    copy?.addEventListener('click', async () => {
        if (!current?.path) return;

        const absolute = new URL(current.path, window.location.origin).href;

        try {
            await navigator.clipboard.writeText(absolute);
            const original = copy.textContent;
            copy.textContent = 'Copied';
            setTimeout(() => {
                copy.textContent = original;
            }, 1200);
        } catch (_) {
            window.prompt('Copy public URL', absolute);
        }
    });
})();
