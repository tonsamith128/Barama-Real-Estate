/* BARAMA_ACCOUNT_SETTINGS_FULL_FUNCTIONS_V1130R3 */
(() => {
    'use strict';

    const normalizedPath = location.pathname.replace(/\/+$/, '');
    const allowed = new Set([
        '/account/settings/basic',
        '/account/settings/profile',
        '/account/settings/privacy'
    ]);
    if (!allowed.has(normalizedPath)) return;

    const $ = (selector, root = document) => root.querySelector(selector);

    const iconText = (text) => {
        const span = document.createElement('span');
        span.setAttribute('aria-hidden', 'true');
        span.textContent = text;
        return span;
    };

    const button = (label, className = '') => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = `barama-account-v1130-button ${className}`.trim();
        el.append(iconText('↗'));
        const copy = document.createElement('span');
        copy.textContent = label;
        el.append(copy);
        return el;
    };

    const actionContainer = (form) => {
        const known = form.querySelector('.barama-settings-actions');
        if (known) {
            known.classList.add('barama-account-v1130-actions');
            return known;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'barama-account-v1130-actions';
        form.append(wrapper);
        return wrapper;
    };

    const findFormContaining = (...selectors) =>
        Array.from(document.forms).find((form) => selectors.every((selector) => form.querySelector(selector)));

    const basic = () => {
        const form = findFormContaining(
            'select[name="preferred_language"]',
            'select[name="timezone"]'
        );
        if (!form || form.dataset.accountV1130Basic === 'ready') return;
        form.dataset.accountV1130Basic = 'ready';

        const language = $('select[name="preferred_language"]', form);
        const timezone = $('select[name="timezone"]', form);
        const actions = actionContainer(form);

        if (!actions.querySelector('[data-account-detect-timezone]')) {
            const detect = button('Use browser timezone');
            detect.dataset.accountDetectTimezone = '1';
            actions.append(detect);

            detect.addEventListener('click', () => {
                let browserTimezone = '';
                try {
                    browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
                } catch (_) {}

                if (!browserTimezone) {
                    window.alert('Your browser did not provide a timezone.');
                    return;
                }

                const option = Array.from(timezone.options)
                    .find((item) => item.value === browserTimezone);

                if (!option) {
                    window.alert(`Browser timezone "${browserTimezone}" is not available in this account timezone list.`);
                    return;
                }

                timezone.value = browserTimezone;
                timezone.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        if (!actions.querySelector('[data-account-reset-defaults]')) {
            const reset = button('Reset defaults');
            reset.dataset.accountResetDefaults = '1';
            reset.classList.add('barama-account-v1130-button--danger');
            actions.append(reset);

            reset.addEventListener('click', () => {
                if (!window.confirm('Reset language to English and timezone to UTC?')) return;

                let action = form.querySelector('input[name="basic_action"]');
                if (!action) {
                    action = document.createElement('input');
                    action.type = 'hidden';
                    action.name = 'basic_action';
                    form.append(action);
                }
                action.value = 'reset';

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        }

        const preview = document.createElement('div');
        preview.className = 'barama-account-v1130-preview';
        preview.innerHTML = `
            <span class="barama-account-v1130-preview__eyebrow">Regional preview</span>
            <strong class="barama-account-v1130-preview__date" data-account-preview-date>Loading preview…</strong>
            <div class="barama-account-v1130-preview__meta">
                <span>Selected timezone: <strong data-account-preview-zone></strong></span>
                <span>Browser timezone: <strong data-account-browser-zone></strong></span>
            </div>
        `;
        actions.insertAdjacentElement('afterend', preview);

        const previewDate = $('[data-account-preview-date]', preview);
        const previewZone = $('[data-account-preview-zone]', preview);
        const browserZone = $('[data-account-browser-zone]', preview);

        let browserTimezone = '';
        try {
            browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (_) {}
        browserZone.textContent = browserTimezone || 'Unavailable';

        const localeFor = (value) => ({
            en: 'en-US',
            km: 'km-KH',
            zh: 'zh-CN'
        }[value] || 'en-US');

        const renderPreview = () => {
            const zone = timezone.value || 'UTC';
            previewZone.textContent = zone;
            try {
                previewDate.textContent = new Intl.DateTimeFormat(localeFor(language.value || 'en'), {
                    dateStyle: 'full',
                    timeStyle: 'short',
                    timeZone: zone
                }).format(new Date());
            } catch (_) {
                previewDate.textContent = 'Preview unavailable';
            }
        };

        language.addEventListener('change', renderPreview);
        timezone.addEventListener('change', renderPreview);
        renderPreview();
    };

    const profile = () => {
        const form = findFormContaining(
            'input[name="name"]',
            'input[name="phone"]'
        ) || findFormContaining('input[name="name"]');

        if (!form || form.dataset.accountV1130Profile === 'ready') return;
        form.dataset.accountV1130Profile = 'ready';

        const actions = actionContainer(form);
        if (actions.querySelector('[data-account-discard-profile]')) return;

        const discard = button('Discard changes');
        discard.dataset.accountDiscardProfile = '1';
        discard.replaceChildren(iconText('↺'), Object.assign(document.createElement('span'), { textContent: 'Discard changes' }));
        actions.append(discard);

        discard.addEventListener('click', () => form.reset());
    };

    const privacy = () => {
        const clearHistoryInput = $('input[name="privacy_action"][value="clear_history"]');
        if (!clearHistoryInput) return;

        const clearHistoryForm = clearHistoryInput.closest('form');
        if (!clearHistoryForm || document.querySelector('[data-account-v1130-privacy-tools]')) return;

        const host =
            clearHistoryForm.closest('.barama-settings-card') ||
            clearHistoryForm.parentElement ||
            clearHistoryForm;

        const panel = document.createElement('section');
        panel.dataset.accountV1130PrivacyTools = '1';
        panel.innerHTML = `
            <div class="barama-account-v1130-data-grid">
                <article class="barama-account-v1130-data-card">
                    <span class="barama-account-v1130-data-card__icon" aria-hidden="true">⇄</span>
                    <strong>Comparison list</strong>
                    <p>Remove all properties currently retained for side-by-side comparison.</p>
                    <form method="post" data-account-clear-compare>
                        <button class="barama-account-v1130-button barama-account-v1130-button--danger" type="submit">
                            Clear comparison
                        </button>
                    </form>
                </article>
                <article class="barama-account-v1130-data-card">
                    <span class="barama-account-v1130-data-card__icon" aria-hidden="true">♡</span>
                    <strong>Saved properties</strong>
                    <p>Review and manage the properties you intentionally saved.</p>
                    <a class="barama-account-v1130-card-action" href="/account/saved">Manage saved properties</a>
                </article>
                <article class="barama-account-v1130-data-card">
                    <span class="barama-account-v1130-data-card__icon" aria-hidden="true">◌</span>
                    <strong>Saved searches & alerts</strong>
                    <p>Manage search alerts independently from your recent-view history.</p>
                    <a class="barama-account-v1130-card-action" href="/account/alerts">Manage alerts</a>
                </article>
            </div>
            <div class="barama-account-v1130-note">
                Clearing recent views or comparisons does not delete saved properties, alerts, inquiries, viewing requests, or your account.
            </div>
        `;

        const compareForm = $('[data-account-clear-compare]', panel);
        compareForm.action = clearHistoryForm.action || location.pathname;

        const csrf =
            clearHistoryForm.querySelector('input[name="_token"]') ||
            clearHistoryForm.querySelector('input[name="csrf_token"]') ||
            clearHistoryForm.querySelector('input[type="hidden"]');

        if (csrf) compareForm.append(csrf.cloneNode(true));

        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'privacy_action';
        action.value = 'clear_compare';
        compareForm.append(action);

        compareForm.addEventListener('submit', (event) => {
            if (!window.confirm('Clear your entire property comparison list?')) {
                event.preventDefault();
            }
        });

        host.append(panel);
    };

    if (normalizedPath === '/account/settings/basic') basic();
    if (normalizedPath === '/account/settings/profile') profile();
    if (normalizedPath === '/account/settings/privacy') privacy();
})();
