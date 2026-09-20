/**
 * Barama Real Estate — canonical application JavaScript
 * Keep first-party browser behavior in this file until a justified split is approved.
 */
(() => {
    'use strict';

    const root = document.documentElement;
    const themeToggle = document.querySelector('[data-theme-toggle]');
    const yearTargets = document.querySelectorAll('[data-current-year]');
    const storageKey = 'barama-theme';

    const preferredTheme = () => {
        const stored = window.localStorage.getItem(storageKey);

        if (stored === 'light' || stored === 'dark') {
            return stored;
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    };

    const applyTheme = (theme) => {
        root.setAttribute('data-bs-theme', theme);

        if (themeToggle) {
            const nextTheme = theme === 'dark' ? 'light' : 'dark';
            themeToggle.setAttribute('aria-label', 'Switch to ' + nextTheme + ' mode');
            themeToggle.setAttribute('title', 'Switch to ' + nextTheme + ' mode');
        }
    };

    applyTheme(preferredTheme());

    themeToggle?.addEventListener('click', () => {
        const current = root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';

        window.localStorage.setItem(storageKey, next);
        applyTheme(next);
    });

    yearTargets.forEach((target) => {
        target.textContent = String(new Date().getFullYear());
    });

    const workspaceOpen = document.querySelector('[data-workspace-open]');
    const workspaceClose = document.querySelector('[data-workspace-close]');
    const workspaceBackdrop = document.querySelector('[data-workspace-backdrop]');
    const workspaceNotice = document.querySelector('[data-workspace-notice]');

    const closeWorkspaceNavigation = () => {
        document.body.classList.remove('workspace-nav-open');
    };

    workspaceOpen?.addEventListener('click', () => {
        document.body.classList.add('workspace-nav-open');
    });

    workspaceClose?.addEventListener('click', closeWorkspaceNavigation);
    workspaceBackdrop?.addEventListener('click', closeWorkspaceNavigation);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeWorkspaceNavigation();
        }
    });

    const showWorkspaceNotice = (message, type) => {
        if (!workspaceNotice) {
            return;
        }

        workspaceNotice.hidden = false;
        workspaceNotice.classList.remove('is-success', 'is-error');
        workspaceNotice.classList.add(type === 'success' ? 'is-success' : 'is-error');
        workspaceNotice.textContent = message;
        workspaceNotice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    document.querySelectorAll('[data-company-action]').forEach((form) => {
        const selector = form.querySelector('[data-company-selector]');

        selector?.addEventListener('change', () => {
            const companyId = selector.value;

            if (!companyId) {
                return;
            }

            form.action = form.action.replace(
                /\/companies\/\d+\/members$/,
                '/companies/' + encodeURIComponent(companyId) + '/members'
            );
        });
    });

    const bulkToggle = document.querySelector('[data-bulk-toggle]');
    const bulkItems = Array.from(document.querySelectorAll('[data-bulk-item]'));
    const bulkCount = document.querySelector('[data-bulk-count]');
    const bulkSubmit = document.querySelector('[data-bulk-submit]');

    const updateBulkState = () => {
        const checked = bulkItems.filter((item) => item.checked).length;

        if (bulkCount) {
            bulkCount.textContent = String(checked);
        }

        if (bulkSubmit) {
            bulkSubmit.disabled = checked === 0;
        }

        if (bulkToggle) {
            bulkToggle.checked = checked > 0 && checked === bulkItems.length;
            bulkToggle.indeterminate = checked > 0 && checked < bulkItems.length;
        }
    };

    bulkToggle?.addEventListener('change', () => {
        bulkItems.forEach((item) => {
            item.checked = bulkToggle.checked;
        });
        updateBulkState();
    });

    bulkItems.forEach((item) => {
        item.addEventListener('change', updateBulkState);
    });

    updateBulkState();

    document.querySelectorAll('[data-workspace-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitter = event.submitter;

            if (form.matches('[data-bulk-form]')) {
                const selected = bulkItems.filter((item) => item.checked).length;

                if (selected < 1) {
                    showWorkspaceNotice('Select at least one record before applying a bulk action.', 'error');
                    return;
                }

                const decision = form.querySelector('[name="decision"]')?.value;

                if (
                    decision === 'reject'
                    && !window.confirm('Reject the selected pending records?')
                ) {
                    return;
                }
            }

            if (
                submitter?.classList.contains('table-action--danger')
                && !window.confirm('Confirm this action?')
            ) {
                return;
            }

            const companySelector = form.querySelector('[data-company-selector]');

            if (companySelector && !companySelector.value) {
                showWorkspaceNotice('Select a company before continuing.', 'error');
                return;
            }

            if (submitter) {
                submitter.disabled = true;
            }

            try {
                const response = await window.fetch(form.action, {
                    method: form.method || 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok || payload?.ok !== true) {
                    throw new Error(payload?.message || 'The request could not be completed.');
                }

                showWorkspaceNotice(
                    payload?.data?.message || 'Changes saved successfully.',
                    'success'
                );

                window.setTimeout(() => {
                    window.location.reload();
                }, 450);
            } catch (error) {
                showWorkspaceNotice(
                    error instanceof Error ? error.message : 'The request could not be completed.',
                    'error'
                );

                if (submitter) {
                    submitter.disabled = false;
                }
            }
        });
    });

    document.querySelectorAll('[data-inquiry-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const notice = form.parentElement?.querySelector('[data-inquiry-notice]');
            const submitter = event.submitter;

            if (submitter) {
                submitter.disabled = true;
            }

            if (notice) {
                notice.hidden = true;
                notice.classList.remove('is-success', 'is-error');
            }

            try {
                const response = await window.fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok || payload?.ok !== true) {
                    throw new Error(payload?.message || 'Your inquiry could not be submitted.');
                }

                if (notice) {
                    notice.hidden = false;
                    notice.classList.add('is-success');
                    notice.textContent = payload?.data?.message || 'Your inquiry has been submitted.';
                }

                form.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => {
                    if (field.name !== 'website') {
                        field.value = '';
                    }
                });
            } catch (error) {
                if (notice) {
                    notice.hidden = false;
                    notice.classList.add('is-error');
                    notice.textContent = error instanceof Error
                        ? error.message
                        : 'Your inquiry could not be submitted.';
                }
            } finally {
                if (submitter) {
                    submitter.disabled = false;
                }
            }
        });
    });

    const accountNotice = document.querySelector('[data-account-notice]');

    const showAccountNotice = (message, type) => {
        if (!accountNotice) {
            return;
        }

        accountNotice.hidden = false;
        accountNotice.classList.remove('is-success', 'is-error');
        accountNotice.classList.add(type === 'success' ? 'is-success' : 'is-error');
        accountNotice.textContent = message;
        accountNotice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    document.querySelectorAll('[data-account-action]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const confirmation = form.getAttribute('data-confirm');

            if (confirmation && !window.confirm(confirmation)) {
                return;
            }

            const submitter = event.submitter;

            if (submitter) {
                submitter.disabled = true;
            }

            try {
                const response = await window.fetch(form.action, {
                    method: form.method || 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok || payload?.ok !== true) {
                    throw new Error(payload?.message || 'The account action could not be completed.');
                }

                showAccountNotice(
                    payload?.data?.message || 'Account activity updated.',
                    'success'
                );

                window.setTimeout(() => {
                    window.location.reload();
                }, 450);
            } catch (error) {
                showAccountNotice(
                    error instanceof Error
                        ? error.message
                        : 'The account action could not be completed.',
                    'error'
                );

                if (submitter) {
                    submitter.disabled = false;
                }
            }
        });
    });
})();
