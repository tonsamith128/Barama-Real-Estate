/* BARAMA REAL ESTATE — FRONTEND JS — stable area bundle. */
(()=>{
"use strict";
const modules=Object.create(null);

/* ===== app ===== */
modules["app"]=function(__baramaScript){
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
        if (stored === 'light' || stored === 'dark') return stored;
        const serverDefault = root.getAttribute('data-barama-default-theme');
        if (serverDefault === 'light' || serverDefault === 'dark') return serverDefault;
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

    const closeWorkspaceNavigation = (restoreFocus = false) => {
        const wasOpen = document.body.classList.contains('workspace-nav-open');

        document.body.classList.remove('workspace-nav-open');
        workspaceOpen?.setAttribute('aria-expanded', 'false');

        if (restoreFocus && wasOpen) {
            workspaceOpen?.focus();
        }
    };

    workspaceOpen?.setAttribute('aria-expanded', 'false');

    workspaceOpen?.addEventListener('click', () => {
        document.body.classList.add('workspace-nav-open');
        workspaceOpen.setAttribute('aria-expanded', 'true');
        workspaceClose?.focus();
    });

    workspaceClose?.addEventListener('click', closeWorkspaceNavigation);
    workspaceBackdrop?.addEventListener('click', closeWorkspaceNavigation);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeWorkspaceNavigation(true);
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
            // BARAMA_CHANGE_CONFIRMATION_COOPERATIVE_V120
            // Change Confirmation owns the first protected submit in capture
            // phase. If it prevented that submit, do not start the workspace
            // AJAX request. Wait for its granted requestSubmit() replay.
            if (event.defaultPrevented) {
                return;
            }

                        // BARAMA_PAYMENT_NATIVE_POST_V128
            // v133 owns the passcode gate. After it grants the replay, allow
            // this marked payment form to submit natively so the server
            // receives the confirmed POST exactly once.
            if (form.matches('[data-payment-native-confirmed-form="1"]')) {
                return;
            }

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
                const response = await window.fetch(
                    (submitter?.hasAttribute('formaction') ? submitter.formAction : form.action),
                    {
                    method: (submitter?.hasAttribute('formmethod') ? submitter.formMethod : form.method) || 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
                );

                const payload = await response.json().catch(() => null);

                if (!response.ok || payload?.ok !== true) {
                    const isUserVerify = /\/dashboard\/users\/\d+\/verify\/?$/.test(new URL(form.action, window.location.href).pathname);
                    let fallback = 'The request could not be completed.';
                    if (isUserVerify) {
                        fallback = response.status === 419 ? 'Your session token expired. Reload this page and try again.'
                            : response.status === 428 ? 'A security change confirmation is required before verifying this user.'
                            : response.status === 403 ? 'Permission denied. Confirm your administrator access.'
                            : response.status === 404 ? 'The Verify endpoint was not found on the server.'
                            : response.status === 500 ? 'The Verify action failed on the server (HTTP 500). Check the PHP error log.'
                            : 'Verify request failed (HTTP ' + response.status + ').';
                    }
                    throw new Error(payload?.message || fallback);
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

                if (form.matches('[data-contact-form]')) {
                    form.reset();
                } else {
                    form.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => {
                        if (field.name !== 'website') {
                            field.value = '';
                        }
                    });
                }
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


/* One-click installer */
(() => {
    'use strict';

    const installer = document.querySelector('[data-installer]');

    if (!installer) {
        return;
    }

    const installerUrl = installer.getAttribute('data-installer-url') || '/installer/';
    const notice = document.querySelector('[data-installer-notice]');
    const licenseForm = document.querySelector('[data-installer-license-form]');
    const purchaseCode = document.querySelector('[data-purchase-code]');
    const agreement = document.querySelector('[data-terms-agreement]');
    const validateButton = document.querySelector('[data-license-validate]');
    const continueButton = document.querySelector('[data-license-continue]');
    const licenseStatus = document.querySelector('[data-license-status]');
    const termsModal = document.querySelector('[data-installer-terms-modal]');
    const installerLanguage = document.querySelector('[data-installer-language]');
    const databaseForm = document.querySelector('[data-installer-database-form]');
    const databaseMode = databaseForm?.querySelector('[data-database-mode]');
    const databaseModePanels = Array.from(
        databaseForm?.querySelectorAll('[data-database-mode-panel]') || []
    );
    const databaseSubmit = databaseForm?.querySelector('[data-database-submit]');
    let licenseValidated = installer.getAttribute('data-license-validated') === '1';
    let termsReviewed = installer.getAttribute('data-terms-reviewed') === '1';

    const languageStorageKey = 'barama-installer-language';

    const applyInstallerLanguage = (language) => {
        if (!installerLanguage) {
            return;
        }

        const supported = Array.from(installerLanguage.options)
            .map((option) => option.value);

        if (!supported.includes(language)) {
            return;
        }

        installerLanguage.value = language;
        document.documentElement.lang = language;
        window.localStorage.setItem(languageStorageKey, language);

        const websiteLanguage = document.querySelector(
            '[name="default_language"]'
        );

        if (websiteLanguage instanceof HTMLSelectElement) {
            const hasOption = Array.from(websiteLanguage.options)
                .some((option) => option.value === language);

            if (hasOption) {
                websiteLanguage.value = language;
            }
        }
    };

    if (installerLanguage) {
        const storedLanguage = window.localStorage.getItem(languageStorageKey);
        applyInstallerLanguage(
            storedLanguage || installerLanguage.value || 'en'
        );

        installerLanguage.addEventListener('change', () => {
            applyInstallerLanguage(installerLanguage.value);
        });
    }

    const updateDatabaseMode = () => {
        if (!databaseForm || !databaseMode) {
            return;
        }

        const mode = databaseMode.value === 'create'
            ? 'create'
            : 'existing';

        databaseModePanels.forEach((panel) => {
            const active = panel.getAttribute('data-database-mode-panel')
                === mode;

            panel.hidden = !active;

            panel.querySelectorAll('input, select').forEach((field) => {
                field.disabled = !active;

                if (!active && field instanceof HTMLInputElement
                    && field.type === 'password') {
                    field.value = '';
                }
            });
        });

        if (databaseSubmit) {
            databaseSubmit.textContent = mode === 'create'
                ? 'Create Database & Continue'
                : 'Test Connection & Continue';
        }
    };

    databaseMode?.addEventListener('change', updateDatabaseMode);
    updateDatabaseMode();

    const showNotice = (message, type = 'error') => {
        if (!notice) {
            return;
        }

        notice.hidden = false;
        notice.classList.remove('is-error', 'is-success');
        notice.classList.add(type === 'success' ? 'is-success' : 'is-error');
        notice.innerHTML = '';
        const span = document.createElement('span');
        span.textContent = message;
        notice.appendChild(span);
    };

    const hideNotice = () => {
        if (notice) {
            notice.hidden = true;
            notice.classList.remove('is-error', 'is-success');
            notice.textContent = '';
        }
    };

    const updateLicenseButtons = () => {
        if (!licenseForm) {
            return;
        }

        const hasCode = (purchaseCode?.value || '').trim().length > 0;
        const accepted = agreement?.checked === true;

        if (validateButton) {
            validateButton.disabled = !hasCode;
        }

        if (continueButton) {
            continueButton.disabled = !(
                licenseValidated
                && termsReviewed
                && accepted
            );
        }
    };

    purchaseCode?.addEventListener('input', () => {
        if (licenseValidated) {
            licenseValidated = false;
            installer.setAttribute('data-license-validated', '0');

            if (licenseStatus) {
                licenseStatus.hidden = true;
                licenseStatus.classList.remove('is-valid', 'is-error');
            }
        }

        updateLicenseButtons();
    });

    agreement?.addEventListener('change', updateLicenseButtons);

    updateLicenseButtons();

    const openTerms = () => {
        if (!termsModal) {
            return;
        }

        termsModal.hidden = false;
        termsModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('installer-modal-open');
        termsModal.querySelector('[data-accept-terms]')?.focus();
    };

    const closeTerms = () => {
        if (!termsModal) {
            return;
        }

        termsModal.hidden = true;
        termsModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('installer-modal-open');
        agreement?.focus();
    };

    agreement?.addEventListener('click', (event) => {
        if (agreement.checked) {
            event.preventDefault();
            agreement.checked = false;
            termsReviewed = false;
            installer.setAttribute('data-terms-reviewed', '0');
            openTerms();
            updateLicenseButtons();
        }
    });
    document.querySelectorAll('[data-close-terms]').forEach((button) => {
        button.addEventListener('click', closeTerms);
    });

    termsModal?.addEventListener('click', (event) => {
        if (event.target === termsModal) {
            closeTerms();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && termsModal && !termsModal.hidden) {
            closeTerms();
        }
    });

    document.querySelector('[data-accept-terms]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const csrf = licenseForm?.querySelector('input[name="_token"]')?.value || '';

        button.disabled = true;
        hideNotice();

        try {
            const body = new FormData();
            body.append('_token', csrf);
            body.append('action', 'accept_terms');

            const response = await fetch(installerUrl, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok || payload?.ok !== true) {
                throw new Error(payload?.message || 'Terms could not be accepted.');
            }

            termsReviewed = true;
            installer.setAttribute('data-terms-reviewed', '1');

            if (agreement) {
                agreement.checked = true;
            }

            closeTerms();
            showNotice(
                payload?.data?.message
                    || 'Terms accepted. You may continue after the license is validated.',
                'success'
            );
            updateLicenseButtons();
        } catch (error) {
            showNotice(
                error instanceof Error ? error.message : 'Terms could not be accepted.'
            );
        } finally {
            button.disabled = false;
        }
    });

    licenseForm?.addEventListener('submit', async (event) => {
        const submitter = event.submitter;

        if (submitter?.value !== 'validate_license') {
            return;
        }

        event.preventDefault();
        hideNotice();
        submitter.disabled = true;

        if (licenseStatus) {
            licenseStatus.hidden = true;
            licenseStatus.classList.remove('is-valid', 'is-error');
        }

        try {
            const body = new FormData(licenseForm);
            body.set('action', 'validate_license');

            const response = await fetch(installerUrl, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok || payload?.ok !== true) {
                throw new Error(
                    payload?.message
                    || 'The purchase code could not be verified.'
                );
            }

            licenseValidated = true;
            installer.setAttribute('data-license-validated', '1');

            if (licenseStatus) {
                licenseStatus.hidden = false;
                licenseStatus.classList.add('is-valid');
                const statusText = licenseStatus.querySelector('span');

                if (statusText) {
                    statusText.textContent = payload?.data?.message
                        || 'License validated successfully.';
                }
            }

            showNotice(
                payload?.data?.message || 'License validated successfully.',
                'success'
            );
        } catch (error) {
            licenseValidated = false;
            installer.setAttribute('data-license-validated', '0');

            if (licenseStatus) {
                licenseStatus.hidden = false;
                licenseStatus.classList.add('is-error');
                const statusText = licenseStatus.querySelector('span');

                if (statusText) {
                    statusText.textContent = error instanceof Error
                        ? error.message
                        : 'The purchase code could not be verified.';
                }
            }

            showNotice(
                error instanceof Error
                    ? error.message
                    : 'The purchase code could not be verified.'
            );
        } finally {
            updateLicenseButtons();
        }
    });

    const finalForm = document.querySelector('[data-installer-final-form]');

    const secureRandom = (max) => {
        const array = new Uint32Array(1);
        window.crypto.getRandomValues(array);

        return array[0] % max;
    };

    const secureShuffle = (characters) => {
        const values = [...characters];

        for (let index = values.length - 1; index > 0; index -= 1) {
            const target = secureRandom(index + 1);
            [values[index], values[target]] = [values[target], values[index]];
        }

        return values.join('');
    };

    document.querySelector('[data-generate-password]')?.addEventListener('click', () => {
        const password = document.querySelector('[data-admin-password]');
        const confirmation = document.querySelector('[data-admin-password-confirmation]');

        if (!password || !confirmation || !window.crypto?.getRandomValues) {
            showNotice('Secure password generation is unavailable in this browser.');
            return;
        }

        const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lower = 'abcdefghijkmnopqrstuvwxyz';
        const numbers = '23456789';
        const symbols = '!@#$%*-_=+?';
        const all = upper + lower + numbers + symbols;
        let generated = upper[secureRandom(upper.length)]
            + lower[secureRandom(lower.length)]
            + numbers[secureRandom(numbers.length)]
            + symbols[secureRandom(symbols.length)];

        while (generated.length < 20) {
            generated += all[secureRandom(all.length)];
        }

        generated = secureShuffle(generated);
        password.value = generated;
        confirmation.value = generated;
        password.type = 'text';
        confirmation.type = 'text';
        password.dispatchEvent(new Event('input', { bubbles: true }));
        showNotice(
            'A strong password was generated. Save it securely before continuing.',
            'success'
        );
        password.focus();
        password.select();
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const wrapper = button.closest('.installer-input');
            const field = wrapper?.querySelector('[data-password-field]');

            if (!field) {
                return;
            }

            const show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            button.setAttribute(
                'aria-label',
                show ? 'Hide password' : 'Show password'
            );
        });
    });

    finalForm?.addEventListener('submit', (event) => {
        if (!event.currentTarget.checkValidity()) {
            return;
        }

        const button = document.querySelector('[data-install-submit]');

        if (button) {
            button.disabled = true;
            button.textContent = 'Installing…';
        }
    });
})();


/* Shared responsive shell and authentication controls */
(() => {
    'use strict';

    const header = document.querySelector('[data-site-header]');
    const menuToggle = document.querySelector('[data-site-menu-toggle]');
    const navigation = document.querySelector('[data-site-navigation]');

    const closeMenu = () => {
        if (!header || !menuToggle) {
            return;
        }

        header.classList.remove('is-menu-open');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open navigation');
    };

    menuToggle?.addEventListener('click', () => {
        if (!header) {
            return;
        }

        const open = !header.classList.contains('is-menu-open');
        header.classList.toggle('is-menu-open', open);
        menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    });

    navigation?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 991) {
            closeMenu();
        }
    });

    document.querySelectorAll('[data-auth-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const wrapper = button.closest('.auth-input');
            const field = wrapper?.querySelector('[data-auth-password]');

            if (!field) {
                return;
            }

            const reveal = field.type === 'password';
            field.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        });
    });

    const secureRandom = (max) => {
        const value = new Uint32Array(1);
        window.crypto.getRandomValues(value);
        return value[0] % max;
    };

    const shuffle = (characters) => {
        const output = [...characters];

        for (let index = output.length - 1; index > 0; index -= 1) {
            const target = secureRandom(index + 1);
            [output[index], output[target]] = [output[target], output[index]];
        }

        return output.join('');
    };

    const updateStrength = (field) => {
        const meter = field?.closest('.auth-form')?.querySelector('[data-auth-password-strength]');

        if (!meter) {
            return;
        }

        const value = field.value || '';
        let score = 0;

        if (value.length >= 12) score += 1;
        if (value.length >= 16) score += 1;
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score += 1;
        if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) score += 1;

        meter.dataset.level = score >= 4 ? 'strong' : (score >= 2 ? 'medium' : (value ? 'weak' : ''));
    };

    document.querySelectorAll('[data-auth-password-primary]').forEach((field) => {
        field.addEventListener('input', () => updateStrength(field));
        updateStrength(field);
    });

    document.querySelectorAll('[data-auth-generate-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('.auth-form');
            const password = form?.querySelector('[data-auth-password-primary]');
            const confirmation = form?.querySelector('[data-auth-password-confirmation]');

            if (!password || !confirmation || !window.crypto?.getRandomValues) {
                return;
            }

            const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            const lower = 'abcdefghijkmnopqrstuvwxyz';
            const numbers = '23456789';
            const symbols = '!@#$%*-_=+?';
            const all = upper + lower + numbers + symbols;
            let generated = upper[secureRandom(upper.length)]
                + lower[secureRandom(lower.length)]
                + numbers[secureRandom(numbers.length)]
                + symbols[secureRandom(symbols.length)];

            while (generated.length < 20) {
                generated += all[secureRandom(all.length)];
            }

            generated = shuffle(generated);
            password.value = generated;
            confirmation.value = generated;
            password.type = 'text';
            confirmation.type = 'text';
            password.dispatchEvent(new Event('input', { bubbles: true }));
            password.focus();
            password.select();
        });
    });
})();


/* Public marketplace discovery interactions */
(() => {
    'use strict';

    document.querySelectorAll('[data-marketplace-filter]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (!form.checkValidity()) {
                return;
            }

            form.classList.add('marketplace-is-loading');
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                const label = button.querySelector('span');

                if (label) {
                    label.textContent = 'Searching…';
                }
            });
        });
    });

    const galleryButtons = document.querySelectorAll('[data-gallery-image]');

    if (galleryButtons.length < 1) {
        return;
    }

    const lightbox = document.createElement('div');
    lightbox.className = 'marketplace-lightbox';
    lightbox.hidden = true;
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', 'Image preview');
    lightbox.innerHTML = [
        '<div class="marketplace-lightbox__frame">',
        '<img alt="">',
        '<button class="marketplace-lightbox__close" type="button" aria-label="Close image preview">×</button>',
        '</div>'
    ].join('');

    document.body.appendChild(lightbox);

    const image = lightbox.querySelector('img');
    const closeButton = lightbox.querySelector('.marketplace-lightbox__close');
    let previousFocus = null;

    const close = () => {
        lightbox.hidden = true;
        document.body.style.overflow = '';

        if (previousFocus instanceof HTMLElement) {
            previousFocus.focus();
        }
    };

    galleryButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const source = button.getAttribute('data-gallery-src');

            if (!source || !image) {
                return;
            }

            previousFocus = button;
            image.src = source;
            image.alt = button.getAttribute('data-gallery-alt') || 'Marketplace image';
            lightbox.hidden = false;
            document.body.style.overflow = 'hidden';
            closeButton?.focus();
        });
    });

    closeButton?.addEventListener('click', close);
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !lightbox.hidden) {
            close();
        }
    });
})();


/* Barama compact auth field labels — keep in canonical app.js */
(() => {
    const enhanceAuthFields = () => {
        document
            .querySelectorAll('.auth-premium--standalone .auth-form__field')
            .forEach((field) => {
                const label = field.querySelector('.auth-label');
                const input = field.querySelector('input, textarea');

                if (!label || !input) {
                    return;
                }

                const labelText = (label.textContent || '').trim();

                if (labelText && !input.getAttribute('placeholder')) {
                    input.setAttribute('placeholder', labelText);
                }

                if (
                    input.type === 'email'
                    && (input.value || '').trim().toLowerCase() === 'you@example.com'
                ) {
                    input.value = '';
                }

                label.classList.add('auth-label--field-placeholder');
            });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceAuthFields, { once: true });
    } else {
        enhanceAuthFields();
    }
})();



/* Barama auth floating labels — canonical app.js only */
(() => {
    const selector = '.auth-premium--standalone .auth-field';

    const hasValue = (input) => {
        if ((input.value || '').trim() !== '') {
            return true;
        }

        try {
            return input.matches(':-webkit-autofill');
        } catch (error) {
            return false;
        }
    };

    const updateField = (field, input) => {
        field.classList.toggle(
            'is-floating',
            document.activeElement === input || hasValue(input)
        );
    };

    const enhanceField = (field) => {
        if (field.dataset.authFloatingReady === '1') {
            return;
        }

        const inputWrap = field.querySelector('.auth-input');
        const input = inputWrap
            ? inputWrap.querySelector('input, textarea, select')
            : null;

        const children = Array.from(field.children);
        const labelTextNode = children.find((child) => {
            return (
                child !== inputWrap
                && child.tagName === 'SPAN'
                && !child.classList.contains('auth-input')
            );
        });

        if (!inputWrap || !input || !labelTextNode) {
            return;
        }

        labelTextNode.classList.add('auth-floating-label');

        /* Move the existing visible label into the existing input shell.
           No PHP markup is changed. */
        inputWrap.insertBefore(labelTextNode, input);

        /* The floating label becomes the visual placeholder. */
        input.setAttribute('placeholder', ' ');

        field.dataset.authFloatingReady = '1';

        const refresh = () => updateField(field, input);

        input.addEventListener('focus', refresh);
        input.addEventListener('blur', refresh);
        input.addEventListener('input', refresh);
        input.addEventListener('change', refresh);

        refresh();

        /* Password managers/autofill can populate shortly after DOM ready. */
        window.setTimeout(refresh, 80);
        window.setTimeout(refresh, 350);
    };

    const enhanceAll = () => {
        document.querySelectorAll(selector).forEach(enhanceField);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceAll, { once: true });
    } else {
        enhanceAll();
    }
})();

/* BARAMA_PUBLIC_UI_HEADER_HERO_FOOTER_V100 */
(() => {
    const onReady = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    const svg = (name) => {
        const icons = {
            home: '<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10M9 21v-6h6v6"/>',
            properties: '<path d="M4 21V9l8-6 8 6v12"/><path d="M8 21v-7h8v7"/><path d="M9 10h6"/>',
            projects: '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>',
            companies: '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10M8 7h4M8 11h4M8 15h4M8 19h4M3 21h18"/>',
            user: '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
            login: '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
            register: '<path d="M15 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>'
        };
        return `<svg viewBox="0 0 24 24" aria-hidden="true">${icons[name] || icons.user}</svg>`;
    };

    const normalizePath = (value) => {
        try {
            const url = new URL(value, window.location.origin);
            const routedPath = url.searchParams.get('_route');
            let path = routedPath || url.pathname || '/';

            if (!path.startsWith('/')) {
                path = '/' + path;
            }

            if (path.length > 1) {
                path = path.replace(/\/+$/, '');
            }

            return path;
        } catch (_) {
            return '/';
        }
    };

    onReady(() => {
        const header = document.querySelector('.site-header');
        if (!header || header.dataset.baramaPublicUiEnhanced === '1') return;
        header.dataset.baramaPublicUiEnhanced = '1';

        const currentPath = normalizePath(window.location.href);
        const navIcons = new Map([
            ['/', 'home'],
            ['/properties', 'properties'],
            ['/projects', 'projects'],
            ['/company', 'companies'],
            ['/companies', 'companies']
        ]);

        header.querySelectorAll('a[href]').forEach((link) => {
            if (link.classList.contains('brand')) return;
            const path = normalizePath(link.href);
            const iconName = navIcons.get(path);
            if (!iconName) return;

            if (!link.querySelector('.barama-nav-icon')) {
                const icon = document.createElement('span');
                icon.className = 'barama-nav-icon';
                icon.innerHTML = svg(iconName);
                link.prepend(icon);
            }

            const active = path === '/'
                ? currentPath === '/'
                : currentPath === path || currentPath.startsWith(`${path}/`);
            if (active) {
                link.classList.add('is-active');
                link.setAttribute('aria-current', 'page');
            }
        });

        /* Existing locale controller/session architecture stays authoritative.
           We only submit the existing form immediately when the select changes. */
        const localeForm = header.querySelector('[data-locale-switch]');
        const localeSelect = header.querySelector('[data-locale-select]');
        const localeSubmit = header.querySelector('[data-locale-submit]');
        if (localeSubmit) {
            localeSubmit.hidden = true;
            localeSubmit.setAttribute('aria-hidden', 'true');
            localeSubmit.tabIndex = -1;
        }
        if (localeForm && localeSelect && localeSelect.dataset.autoSubmitBound !== '1') {
            localeSelect.dataset.autoSubmitBound = '1';
            localeSelect.addEventListener('change', () => {
                if (typeof localeForm.requestSubmit === 'function') {
                    localeForm.requestSubmit();
                } else {
                    localeForm.submit();
                }
            });
        }

        const wrap = header.querySelector('[data-public-user-menu]');
        const trigger = wrap?.querySelector('[data-public-user-trigger]');
        const menu = wrap?.querySelector('[data-public-user-panel]');

        if (wrap && trigger && menu) {
            const setOpen = (open) => {
                menu.hidden = !open;
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            trigger.addEventListener('click', (event) => {
                event.stopPropagation();
                setOpen(menu.hidden);
            });

            document.addEventListener('click', (event) => {
                if (!wrap.contains(event.target)) setOpen(false);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !menu.hidden) {
                    setOpen(false);
                    trigger.focus();
                }
            });
        }
    });
})();

/* BARAMA_SINGLE_DETAIL_PAGES_V100 */
(() => {
    'use strict';

    const stages = document.querySelectorAll('[data-detail-media-stage]');

    stages.forEach((stage) => {
        const slides = Array.from(stage.querySelectorAll('[data-detail-media-slide]'));
        const thumbs = Array.from(stage.querySelectorAll('[data-detail-media-thumb]'));

        if (slides.length < 1 || thumbs.length < 1) {
            return;
        }

        let activeIndex = Math.max(
            0,
            slides.findIndex((slide) => slide.classList.contains('is-active'))
        );

        const activate = (index, focusThumb = false) => {
            if (!Number.isInteger(index) || index < 0 || index >= slides.length) {
                return;
            }

            slides.forEach((slide, slideIndex) => {
                const active = slideIndex === index;
                slide.hidden = !active;
                slide.classList.toggle('is-active', active);

                if (!active) {
                    slide.querySelectorAll('video').forEach((video) => {
                        try {
                            video.pause();
                        } catch (_) {
                            // Ignore media pause failures.
                        }
                    });
                }
            });

            thumbs.forEach((thumb, thumbIndex) => {
                const active = thumbIndex === index;
                thumb.classList.toggle('is-active', active);
                thumb.setAttribute('aria-selected', active ? 'true' : 'false');
                thumb.tabIndex = active ? 0 : -1;
            });

            activeIndex = index;

            if (focusThumb) {
                thumbs[index]?.focus();
            }
        };

        thumbs.forEach((thumb, index) => {
            thumb.addEventListener('click', () => activate(index));

            thumb.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                    return;
                }

                event.preventDefault();

                let next = activeIndex;

                if (event.key === 'ArrowRight') {
                    next = (activeIndex + 1) % thumbs.length;
                } else if (event.key === 'ArrowLeft') {
                    next = (activeIndex - 1 + thumbs.length) % thumbs.length;
                } else if (event.key === 'Home') {
                    next = 0;
                } else if (event.key === 'End') {
                    next = thumbs.length - 1;
                }

                activate(next, true);
            });
        });

        activate(activeIndex);
    });
})();

/* BARAMA_WORKSPACE_NAV_TOGGLE_V100 */
(() => {
    'use strict';

    const root = document.documentElement;
    const button = document.querySelector('[data-workspace-sidebar-toggle]');
    if (!button) return;

    const desktop = window.matchMedia('(min-width: 992px)');
    const storageKey = 'barama.workspace.sidebar.collapsed';

    const readStored = () => {
        try { return window.localStorage.getItem(storageKey) === '1'; }
        catch (_) { return false; }
    };

    const writeStored = (collapsed) => {
        try { window.localStorage.setItem(storageKey, collapsed ? '1' : '0'); }
        catch (_) { /* storage may be unavailable; UI still works for this page */ }
    };

    const applyDesktopState = (collapsed, persist = false) => {
        if (!desktop.matches) {
            root.classList.remove('workspace-sidebar-is-collapsed');
            return;
        }

        root.classList.toggle('workspace-sidebar-is-collapsed', collapsed);
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        if (persist) writeStored(collapsed);
    };

    applyDesktopState(readStored());

    button.addEventListener('click', (event) => {
        if (!desktop.matches) return; // Existing mobile drawer handler remains authoritative.

        event.preventDefault();
        event.stopImmediatePropagation();
        const nextCollapsed = !root.classList.contains('workspace-sidebar-is-collapsed');
        applyDesktopState(nextCollapsed, true);
    }, true);

    const handleBreakpointChange = () => {
        if (desktop.matches) {
            applyDesktopState(readStored());
        } else {
            root.classList.remove('workspace-sidebar-is-collapsed');
        }
    };

    if (typeof desktop.addEventListener === 'function') {
        desktop.addEventListener('change', handleBreakpointChange);
    } else if (typeof desktop.addListener === 'function') {
        desktop.addListener(handleBreakpointChange);
    }
})();

/* BARAMA_WORKSPACE_SIDEBAR_NAV_REFINEMENT_V100 */
(() => {
    'use strict';

    const sidebar = document.querySelector('.workspace-sidebar');
    if (!sidebar) return;

    sidebar.setAttribute('data-workspace-nav-refined', 'v1.0.0');

    const nav = sidebar.querySelector('nav');
    if (!nav) return;

    nav.classList.add('workspace-sidebar__navigation-refined');

    const links = Array.from(nav.querySelectorAll('a[href]'));
    if (!links.length) return;

    const currentPath = (() => {
        try {
            const path = new URL(window.location.href).pathname;
            return path.length > 1 ? path.replace(/\/+$/, '') : '/';
        } catch (_) {
            return window.location.pathname || '/';
        }
    })();

    let current = links.find((link) => link.getAttribute('aria-current') === 'page');

    if (!current) {
        current = links.find((link) => {
            try {
                const path = new URL(link.href, window.location.origin).pathname;
                const normalized = path.length > 1 ? path.replace(/\/+$/, '') : '/';
                return normalized === currentPath;
            } catch (_) {
                return false;
            }
        });
    }

    if (current) {
        current.classList.add('is-current');
        if (!current.hasAttribute('aria-current')) current.setAttribute('aria-current', 'page');
    }

    // Preserve server-rendered labels while giving icon-only/short links useful tooltips.
    links.forEach((link) => {
        if (link.hasAttribute('title')) return;
        const text = (link.textContent || '').replace(/\s+/g, ' ').trim();
        if (text) link.setAttribute('title', text);
    });
})();

/* BARAMA_WORKSPACE_HEADER_UTILITY_ICON_FIX_V100
   Locale forms apply immediately after selection. Existing public-header binding
   is respected through the shared data-auto-submit-bound guard.
*/
(() => {
    document.querySelectorAll('form[data-locale-switch]').forEach((form) => {
        const select = form.querySelector('[data-locale-select]');
        if (!(select instanceof HTMLSelectElement)) return;
        if (select.dataset.autoSubmitBound === '1') return;
        select.dataset.autoSubmitBound = '1';
        select.addEventListener('change', () => {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });
})();


/* BARAMA_PRE_DEMO_CONTENT_DISCOVERY_V100 */
(() => {
    'use strict';

    const dialogs = Array.from(document.querySelectorAll('[data-config-dialog]'));

    /* BARAMA_APPEARANCE_FEEDBACK_V117A11
       Display a fixed, user-dismissible status without scrolling the workspace.
       Preserve successful AJAX feedback across the backend-confirming reload. */
    const appearanceHost = document.querySelector('.appearance-manager');
    const appearanceNotice = appearanceHost?.querySelector('[data-workspace-notice]') || null;
    const feedbackKey = 'barama-appearance-feedback-v117a12';
    let appearanceNoticeTimer = null;
    const clearNoticeTimer = () => {
        if (appearanceNoticeTimer !== null) {
            window.clearTimeout(appearanceNoticeTimer);
            appearanceNoticeTimer = null;
        }
    };
    const hideAppearanceNotice = () => {
        clearNoticeTimer();
        if (appearanceNotice) appearanceNotice.hidden = true;
    };
    const scheduleNoticeClose = (type) => {
        clearNoticeTimer();
        if (!appearanceNotice || appearanceNotice.hidden) return;
        appearanceNoticeTimer = window.setTimeout(hideAppearanceNotice, type === 'error' ? 15000 : 10000);
    };
    const showAppearanceNotice = (message, type = 'success') => {
        if (!appearanceNotice) return;
        const isError = type === 'error';
        appearanceNotice.hidden = false;
        appearanceNotice.classList.toggle('is-success', !isError);
        appearanceNotice.classList.toggle('is-error', isError);
        appearanceNotice.setAttribute('role', isError ? 'alert' : 'status');
        appearanceNotice.setAttribute('aria-live', isError ? 'assertive' : 'polite');
        const icon = document.createElement('span');
        icon.className = 'appearance-toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = isError ? '!' : '✓';
        const body = document.createElement('span');
        body.className = 'appearance-toast__message';
        body.textContent = message;
        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'appearance-toast__dismiss';
        dismiss.setAttribute('aria-label', 'Dismiss appearance notification');
        dismiss.textContent = '×';
        dismiss.addEventListener('click', hideAppearanceNotice);
        appearanceNotice.replaceChildren(icon, body, dismiss);
        scheduleNoticeClose(type);
    };
    if (appearanceNotice) {
        appearanceNotice.addEventListener('mouseenter', clearNoticeTimer);
        appearanceNotice.addEventListener('mouseleave', () => scheduleNoticeClose(appearanceNotice.classList.contains('is-error') ? 'error' : 'success'));
        appearanceNotice.addEventListener('focusin', clearNoticeTimer);
        appearanceNotice.addEventListener('focusout', () => scheduleNoticeClose(appearanceNotice.classList.contains('is-error') ? 'error' : 'success'));
        let saved = null;
        try {
            const serialized = window.sessionStorage.getItem(feedbackKey);
            window.sessionStorage.removeItem(feedbackKey);
            if (serialized) saved = JSON.parse(serialized);
        } catch (_) { /* Private browsing may disable storage; page still works. */ }
        if (saved && typeof saved.message === 'string' && Date.now() - Number(saved.time || 0) < 60000) {
            const feature = typeof saved.feature === 'string' ? saved.feature : '';
            const expected = typeof saved.enabled === 'boolean' ? saved.enabled : null;
            const form = feature ? Array.from(document.querySelectorAll('[data-toggle-form]')).find(
                (item) => item.action.endsWith('/' + feature + '/toggle')
            ) : null;
            const toggle = form?.querySelector('[data-auto-toggle]');
            if (expected !== null && (!(toggle instanceof HTMLInputElement) || toggle.checked !== expected)) {
                const label = feature.replace(/_/g, ' ');
                const expectedText = expected ? 'ON' : 'OFF';
                const actualText = toggle instanceof HTMLInputElement ? (toggle.checked ? 'ON' : 'OFF') : 'unavailable';
                showAppearanceNotice('Could not verify ' + label + ' as ' + expectedText + '. Current server-rendered state: ' + actualText + '. Please retry or reload.', 'error');
            } else {
                showAppearanceNotice(saved.message, saved.type === 'error' ? 'error' : 'success');
            }
        } else if (!appearanceNotice.hidden) {
            const serverMessage = (appearanceNotice.textContent || '').trim();
            if (serverMessage) showAppearanceNotice(serverMessage, appearanceNotice.classList.contains('is-error') ? 'error' : 'success');
        }
    }

    // BARAMA_APPEARANCE_PASSCODE_CANCEL_RECOVERY_V117A13
    // Restore the last confirmed state when the security dialog is cancelled.
    // The security handler emits this event ONLY on cancellation, never when
    // it closes the dialog to replay an approved protected request.
    document.addEventListener('barama:change-confirmation-cancelled', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-appearance-form][data-toggle-form]')) return;
        if (form.dataset.appearanceSaving === '1') return;
        const toggle = form.querySelector('[data-auto-toggle]');
        const hidden = form.querySelector('[data-toggle-value]');
        if (!(toggle instanceof HTMLInputElement) || !(hidden instanceof HTMLInputElement)) return;
        const confirmed = toggle.dataset.appearanceConfirmed === '1';
        toggle.checked = confirmed;
        toggle.disabled = false;
        hidden.value = confirmed ? '1' : '0';
        const label = form.querySelector('.premium-switch b');
        if (label) label.textContent = confirmed ? 'ON' : 'OFF';
        delete form.dataset.appearanceRequested;
    });

    document.querySelectorAll('[data-appearance-form]').forEach((form) => {
        if (form.dataset.appearanceFormBound === '1') {
            return;
        }

        form.dataset.appearanceFormBound = '1';

        form.addEventListener('submit', async (event) => {
            // BARAMA_APPEARANCE_SECURITY_REPLAY_V117A13
            // The change-confirmation capture listener prevents the first submit.
            // Never send an unapproved AJAX request; wait for its one-time grant
            // and requestSubmit() replay before making the single real save.
            if (event.defaultPrevented) return;
            event.preventDefault();
            if (form.dataset.appearanceSaving === '1') return;
            form.dataset.appearanceSaving = '1';

            const submitter = event.submitter;
            const dialog = form.closest('[data-config-dialog]');
            const toggle = form.querySelector('[data-auto-toggle]');
            const name = form.closest('tr')?.querySelector('.content-config-feature strong')?.textContent?.trim() || 'Appearance';
            const feature = form.hasAttribute('data-toggle-form')
                ? form.action.split('/').slice(-2, -1)[0] : '';
            /* BARAMA_APPEARANCE_TOGGLE_REQUEST_CONFIRM_V117A12 */
            // Use the explicit state captured by the switch change event, not a stale DOM value.
            const explicitValue = form.dataset.appearanceRequested;
            const enabled = toggle instanceof HTMLInputElement
                ? (explicitValue === '0' || explicitValue === '1' ? explicitValue === '1' : toggle.checked)
                : null;
            const previousEnabled = toggle instanceof HTMLInputElement
                ? toggle.dataset.appearanceConfirmed === '1' : null;
            if (toggle instanceof HTMLInputElement) toggle.disabled = true;

            const dialogNotice = (() => {
                if (!(dialog instanceof HTMLDialogElement)) {
                    return null;
                }

                let notice = dialog.querySelector('[data-appearance-dialog-notice]');

                if (notice) {
                    return notice;
                }

                notice = document.createElement('div');
                notice.className = 'config-dialog__notice';
                notice.hidden = true;
                notice.setAttribute('data-appearance-dialog-notice', '');

                const footer = dialog.querySelector('.config-dialog__footer');
                footer?.before(notice);

                return notice;
            })();

            const showDialogNotice = (message, type = 'error') => {
                if (!dialogNotice) {
                    return;
                }

                dialogNotice.hidden = false;
                dialogNotice.classList.remove('is-success', 'is-error');
                dialogNotice.classList.add(type === 'success' ? 'is-success' : 'is-error');
                dialogNotice.textContent = message;
            };

            const clearDialogNotice = () => {
                if (!dialogNotice) {
                    return;
                }

                dialogNotice.hidden = true;
                dialogNotice.classList.remove('is-success', 'is-error');
                dialogNotice.textContent = '';
            };

            if (
                submitter?.classList.contains('table-action--danger')
                && !window.confirm('Reset frontend appearance to Barama defaults?')
            ) {
                form.dataset.appearanceSaving = '0';
                if (toggle instanceof HTMLInputElement) toggle.disabled = false;
                return;
            }

            clearDialogNotice();

            if (typeof window.fetch !== 'function') {
                HTMLFormElement.prototype.submit.call(form);
                return;
            }

            if (submitter) {
                submitter.disabled = true;
                submitter.setAttribute('aria-busy', 'true');
            }

            try {
                const body = new FormData(form);
                if (feature && enabled !== null) {
                    // The unchecked checkbox has no name; always send the definitive 0/1 value.
                    body.set('enabled', enabled ? '1' : '0');
                }
                const response = await window.fetch(
                    (submitter?.hasAttribute('formaction') ? submitter.formAction : form.action),
                    {
                        method: (submitter?.hasAttribute('formmethod') ? submitter.formMethod : form.method) || 'POST',
                        body,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                );

                const responseText = await response.text();
                let payload = null;

                if (responseText.trim() !== '') {
                    try {
                        payload = JSON.parse(responseText);
                    } catch (_) {
                        payload = null;
                    }
                }

                if (!response.ok || payload?.ok !== true) {
                    const fallback = response.status === 419
                        ? 'Your session token expired. Refresh this page and try again.'
                        : 'Appearance could not be saved.';

                    throw new Error(payload?.message || fallback);
                }

                // Success is not based on HTTP 200 alone: verify the backend's persisted state.
                if (feature && enabled !== null && (
                    payload?.data?.feature !== feature
                    || typeof payload?.data?.enabled !== 'boolean'
                    || payload.data.enabled !== enabled
                )) {
                    throw new Error('The server did not confirm ' + feature.replace(/_/g, ' ') + ' as ' + (enabled ? 'ON' : 'OFF') + '. No success was recorded. Refresh the page and verify the saved setting.');
                }
                if (dialog instanceof HTMLDialogElement && dialog.open) {
                    dialog.close();
                }

                const message = feature && enabled !== null
                    ? name + ' ' + (enabled ? 'enabled' : 'disabled') + '. Saved successfully. Refresh the public frontend to review the effect.'
                    : (payload?.data?.message || 'Appearance changes saved successfully.');
                if (toggle instanceof HTMLInputElement) toggle.dataset.appearanceConfirmed = enabled ? '1' : '0';
                showAppearanceNotice(message, 'success');
                let feedbackStored = false;
                try {
                    window.sessionStorage.setItem(feedbackKey, JSON.stringify({
                        message, type: 'success', feature, enabled, time: Date.now()
                    }));
                    feedbackStored = true;
                } catch (_) { /* Fallback keeps the toast visible before reloading. */ }
                window.setTimeout(() => window.location.reload(), feedbackStored ? 300 : 6000);
            } catch (error) {
                showDialogNotice(
                    error instanceof Error
                        ? error.message
                        : 'Appearance could not be saved.',
                    'error'
                );

                showAppearanceNotice(error instanceof Error ? error.message : 'Appearance could not be saved.', 'error');
                if (toggle instanceof HTMLInputElement && previousEnabled !== null) {
                    toggle.checked = previousEnabled;
                    const hiddenValue = form.querySelector('[data-toggle-value]');
                    if (hiddenValue instanceof HTMLInputElement) hiddenValue.value = previousEnabled ? '1' : '0';
                    const stateLabel = form.querySelector('.premium-switch b');
                    if (stateLabel) stateLabel.textContent = previousEnabled ? 'ON' : 'OFF';
                }
            } finally {
                delete form.dataset.appearanceRequested;
                form.dataset.appearanceSaving = '0';
                if (toggle instanceof HTMLInputElement) toggle.disabled = false;
                if (submitter) {
                    submitter.disabled = false;
                    submitter.removeAttribute('aria-busy');
                }
            }
        });
    });

    document.querySelectorAll('[data-configure-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-configure-open');
            const dialog = id ? document.getElementById(id) : null;

            if (!(dialog instanceof HTMLDialogElement)) {
                return;
            }

            if (!dialog.open) {
                dialog.showModal();
            }

            window.setTimeout(() => {
                dialog.querySelector('input:not([type="hidden"]), select, textarea, button')
                    ?.focus();
            }, 0);
        });
    });

    dialogs.forEach((dialog) => {
        dialog.querySelectorAll('[data-configure-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });

    document.querySelectorAll('[data-toggle-form]').forEach((form) => {
        const input = form.querySelector('[data-auto-toggle]');
        const value = form.querySelector('[data-toggle-value]');
        const label = form.querySelector('.premium-switch b');

        if (!(input instanceof HTMLInputElement) || !(value instanceof HTMLInputElement)) {
            return;
        }

        input.dataset.appearanceConfirmed = input.checked ? '1' : '0';
        input.addEventListener('change', () => {
            if (form.dataset.appearanceSaving === '1') return;
            // State transitions are based on the last confirmed server rendering.
            const requested = input.dataset.appearanceConfirmed !== '1';
            input.checked = requested;
            const target = requested ? '1' : '0';
            form.dataset.appearanceRequested = target;
            value.value = target;

            if (label) {
                label.textContent = requested ? 'ON' : 'OFF';
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        });
    });

    document.querySelectorAll('[data-hero-background-type]').forEach((select) => {
        const dialog = select.closest('[data-config-dialog]');
        if (!dialog) return;

        const panels = Array.from(
            dialog.querySelectorAll('[data-hero-background-panel]')
        );

        const update = () => {
            panels.forEach((panel) => {
                panel.hidden = panel.getAttribute('data-hero-background-panel') !== select.value;
            });
        };

        select.addEventListener('change', update);
        update();
    });
})();


/* BARAMA_HOMEPAGE_HERO_SLIDER_V100 */
(() => {
    'use strict';

    const reducedMotion = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
        : false;

    document.querySelectorAll('[data-hero-slider]').forEach((slider) => {
        const slides = Array.from(slider.querySelectorAll('.hero-slider__slide'));

        if (slides.length < 2) {
            return;
        }

        const autoplay = slider.getAttribute('data-hero-slider-autoplay') === '1';

        if (!autoplay || reducedMotion) {
            return;
        }

        const requested = Number.parseInt(
            slider.getAttribute('data-hero-slider-interval') || '6000',
            10
        );
        const interval = Number.isFinite(requested)
            ? Math.max(2500, Math.min(20000, requested))
            : 6000;

        let index = Math.max(
            0,
            slides.findIndex((slide) => slide.classList.contains('is-active'))
        );
        let timer = null;

        const show = (next) => {
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('is-active', slideIndex === next);
            });
            index = next;
        };

        const start = () => {
            if (timer !== null || document.hidden) {
                return;
            }

            timer = window.setInterval(() => {
                show((index + 1) % slides.length);
            }, interval);
        };

        const stop = () => {
            if (timer === null) {
                return;
            }

            window.clearInterval(timer);
            timer = null;
        };

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stop();
            } else {
                start();
            }
        });

        start();
    });
})();


/* BARAMA_HOMEPAGE_SUBSCRIBE_V100 */
(() => {
    'use strict';

    document.querySelectorAll('[data-home-subscribe-form]').forEach((form) => {
        const notice = form.parentElement?.querySelector('[data-home-subscribe-notice]');
        const successMessage = form.getAttribute('data-success-message')
            || 'Thank you. You are subscribed to Barama updates.';

        form.addEventListener('submit', async (event) => {
            if (typeof window.fetch !== 'function') {
                return;
            }

            event.preventDefault();

            const submitter = event.submitter;

            if (submitter) {
                submitter.disabled = true;
                submitter.setAttribute('aria-busy', 'true');
            }

            if (notice) {
                notice.hidden = true;
                notice.classList.remove('is-success', 'is-error');
                notice.textContent = '';
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
                    throw new Error(
                        payload?.message || 'Subscription could not be completed.'
                    );
                }

                if (notice) {
                    notice.hidden = false;
                    notice.classList.add('is-success');
                    notice.textContent = successMessage;
                }

                const email = form.querySelector('input[name="email"]');
                if (email instanceof HTMLInputElement) {
                    email.value = '';
                }
            } catch (error) {
                if (notice) {
                    notice.hidden = false;
                    notice.classList.add('is-error');
                    notice.textContent = error instanceof Error
                        ? error.message
                        : 'Subscription could not be completed.';
                }
            } finally {
                if (submitter) {
                    submitter.disabled = false;
                    submitter.removeAttribute('aria-busy');
                }
            }
        });
    });
})();

/* BARAMA_WORKSPACE_FLOATING_ROW_ACTIONS_V100 */
(() => {
    'use strict';

    const init = () => {
        let active = null;

        const closeMenu = () => {
            if (!active) return;

            const { owner, trigger, menu } = active;
            menu.hidden = true;
            menu.style.top = '';
            menu.style.left = '';
            menu.style.visibility = '';
            owner.appendChild(menu);
            trigger.setAttribute('aria-expanded', 'false');
            active = null;
        };

        const positionMenu = () => {
            if (!active) return;

            const { trigger, menu } = active;
            const rect = trigger.getBoundingClientRect();
            const gap = 6;
            const edge = 8;

            menu.hidden = false;
            menu.style.visibility = 'hidden';

            const width = menu.offsetWidth;
            const height = menu.offsetHeight;
            let left = rect.right - width;
            let top = rect.bottom + gap;

            left = Math.max(edge, Math.min(left, window.innerWidth - width - edge));

            if (top + height > window.innerHeight - edge) {
                top = rect.top - height - gap;
            }

            top = Math.max(edge, Math.min(top, window.innerHeight - height - edge));

            menu.style.left = Math.round(left) + 'px';
            menu.style.top = Math.round(top) + 'px';
            menu.style.visibility = '';
        };

        document.querySelectorAll('[data-workspace-row-actions-trigger]').forEach((trigger) => {
            const owner = trigger.closest('[data-workspace-row-actions]');
            const menuId = trigger.getAttribute('aria-controls');
            const menu = menuId ? document.getElementById(menuId) : null;

            if (!owner || !menu) return;

            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (active?.trigger === trigger) {
                    closeMenu();
                    return;
                }

                closeMenu();
                active = { owner, trigger, menu };
                trigger.setAttribute('aria-expanded', 'true');
                document.body.appendChild(menu);
                positionMenu();
            });
        });

        document.addEventListener('click', (event) => {
            if (!active) return;
            if (active.menu.contains(event.target) || active.trigger.contains(event.target)) return;
            closeMenu();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !active) return;
            const trigger = active.trigger;
            closeMenu();
            trigger.focus();
        });

        window.addEventListener('resize', positionMenu);
        window.addEventListener('scroll', closeMenu, true);

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy-slug]');
            if (!button) return;

            event.preventDefault();
            const value = button.getAttribute('data-copy-value') || '';
            if (!value) return;

            const original = button.textContent;

            try {
                await navigator.clipboard.writeText(value);
                button.textContent = 'Copied';
            } catch (_) {
                button.textContent = 'Copy failed';
            }

            window.setTimeout(() => {
                button.textContent = original;
            }, 1200);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

/* BARAMA_WORKSPACE_RICH_DESCRIPTION_EDITOR_V100 */
(() => {
    'use strict';

    const safeLink = (raw) => {
        let value = String(raw || '').trim();

        if (value === '') return '';
        if (/^#[A-Za-z0-9._:-]+$/.test(value)) return value;
        if (/^\/(?!\/)/.test(value)) return value;

        if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return 'mailto:' + value;
        }

        if (/^\+?[0-9][0-9+().\-\s]{6,}$/.test(value)) {
            return 'tel:' + value;
        }

        if (/^mailto:/i.test(value)) {
            const address = value.slice(7).split('?')[0].trim();
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(address) ? value : null;
        }

        if (/^tel:/i.test(value)) {
            return /^tel:[0-9+().\-\s]+$/i.test(value) ? value : null;
        }

        if (!/^[A-Za-z][A-Za-z0-9+.-]*:/.test(value)) {
            value = 'https://' + value;
        }

        try {
            const parsed = new URL(value, window.location.origin);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:'
                ? parsed.href
                : null;
        } catch (_) {
            return null;
        }
    };

    const initialize = (editor) => {
        if (editor.dataset.richTextReady === 'true') return;

        const source = editor.querySelector('[data-rich-text-source]');
        const shell = editor.querySelector('[data-rich-text-shell]');
        const surface = editor.querySelector('[data-rich-text-surface]');
        const toolbar = editor.querySelector('[data-rich-text-toolbar]');

        if (!source || !shell || !surface || !toolbar) return;

        editor.dataset.richTextReady = 'true';
        source.hidden = true;
        shell.hidden = false;

        const sync = () => {
            source.value = surface.innerHTML.trim();
        };

        const selectionInsideSurface = () => {
            const selection = window.getSelection();
            const anchor = selection?.anchorNode;
            return Boolean(anchor && (anchor === surface || surface.contains(anchor)));
        };

        const hardenLinks = () => {
            surface.querySelectorAll('a[href]').forEach((anchor) => {
                const href = anchor.getAttribute('href') || '';
                if (/^https?:/i.test(href)) {
                    anchor.setAttribute('target', '_blank');
                    anchor.setAttribute('rel', 'noopener noreferrer');
                } else {
                    anchor.removeAttribute('target');
                    anchor.removeAttribute('rel');
                }
            });
        };

        const updateToolbarState = () => {
            if (!selectionInsideSurface()) return;

            toolbar.querySelectorAll('[data-rich-toggle]').forEach((button) => {
                const command = button.getAttribute('data-rich-command');
                const block = button.getAttribute('data-rich-block');
                let active = false;

                try {
                    if (command) {
                        active = Boolean(document.queryCommandState(command));
                    } else if (block) {
                        const value = String(document.queryCommandValue('formatBlock') || '')
                            .replace(/[<>]/g, '')
                            .toLowerCase();
                        active = value === block.toLowerCase();
                    }
                } catch (_) {
                    active = false;
                }

                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        };

        const run = (button) => {
            const command = button.getAttribute('data-rich-command');
            const block = button.getAttribute('data-rich-block');

            surface.focus();

            if (block) {
                document.execCommand('formatBlock', false, block);
                sync();
                updateToolbarState();
                return;
            }

            if (!command) return;

            if (command === 'createLink') {
                const selection = window.getSelection();

                if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
                    window.alert('Select the text you want to turn into a link first.');
                    return;
                }

                const input = window.prompt('Enter a link URL, email, phone number, or internal path:');
                if (input === null) return;

                if (input.trim() === '') {
                    document.execCommand('unlink', false, null);
                } else {
                    const href = safeLink(input);

                    if (href === null) {
                        window.alert('Please enter a valid HTTP(S), email, phone, internal, or anchor link.');
                        return;
                    }

                    document.execCommand('createLink', false, href);
                    hardenLinks();
                }
            } else {
                document.execCommand(command, false, null);
            }

            sync();
            updateToolbarState();
        };

        toolbar.querySelectorAll('button[data-rich-command], button[data-rich-block]').forEach((button) => {
            button.addEventListener('pointerdown', (event) => event.preventDefault());
            button.addEventListener('click', () => run(button));
        });

        surface.addEventListener('input', () => {
            sync();
            updateToolbarState();
        });

        surface.addEventListener('paste', (event) => {
            const text = event.clipboardData?.getData('text/plain');
            if (typeof text !== 'string') return;

            event.preventDefault();
            document.execCommand('insertText', false, text);
            sync();
        });

        ['keyup', 'mouseup', 'focus'].forEach((eventName) => {
            surface.addEventListener(eventName, updateToolbarState);
        });

        document.addEventListener('selectionchange', updateToolbarState);
        editor.closest('form')?.addEventListener('submit', sync, true);
    };

    const init = () => {
        document.querySelectorAll('[data-rich-text-editor]').forEach(initialize);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

/* BARAMA_PROFILE_THEME_CONTROL_V100
 * User-feedback refinement:
 * Move the existing canonical light/dark control into the bottom of the
 * public Profile/Account dropdown. This does NOT implement a second theme
 * engine. The existing [data-theme-toggle] node is moved intact so its
 * current runtime, storage and prepaint behavior remain authoritative.
 */
(() => {
    'use strict';

    const root = document.documentElement;
    const RELOCATED = 'data-barama-profile-theme-relocated';

    const publicHeader = () =>
        document.querySelector('header.site-header, header [class*="site-header"]')?.closest('header')
        || document.querySelector('header');

    const canonicalToggle = () => {
        const preferred = [
            '.site-footer [data-theme-toggle]',
            '.market-home [data-theme-toggle]',
            'main [data-theme-toggle]',
            'header [data-theme-toggle]',
            '[data-theme-toggle]'
        ];
        for (const selector of preferred) {
            const node = document.querySelector(selector);
            if (node instanceof HTMLElement) return node;
        }
        return null;
    };

    const normalizedPath = (anchor) => {
        try {
            return new URL(anchor.href, window.location.href).pathname.replace(/\/+$/, '') || '/';
        } catch (_) {
            return String(anchor.getAttribute('href') || '').split('?')[0].replace(/\/+$/, '') || '/';
        }
    };

    const findAnchor = (scope, paths) =>
        Array.from(scope.querySelectorAll('a[href]')).find((anchor) =>
            paths.includes(normalizedPath(anchor))
        ) || null;

    const commonAncestor = (a, b, stop) => {
        if (!a || !b) return null;
        let node = a.parentElement;
        while (node && node !== stop && node !== document.body) {
            if (node.contains(b)) return node;
            node = node.parentElement;
        }
        return null;
    };

    const menuFromKnownClasses = (header) => {
        const candidates = header.querySelectorAll(
            '.profile-menu, .account-menu, .header-profile-menu, .header-account-menu, ' +
            '.profile-dropdown, .account-dropdown, [data-profile-menu], [data-account-menu], ' +
            '.dropdown-menu, [role="menu"]'
        );

        for (const menu of candidates) {
            const login = findAnchor(menu, ['/login', '/signin']);
            const register = findAnchor(menu, ['/register', '/signup']);
            const account = findAnchor(menu, ['/account', '/dashboard']);
            const logout = menu.querySelector(
                'form[action*="/logout"], a[href*="/logout"], button[data-logout]'
            );

            if ((login && register) || (account && logout)) {
                return menu;
            }
        }

        return null;
    };

    const menuFromAuthLinks = (header) => {
        const login = findAnchor(header, ['/login', '/signin']);
        const register = findAnchor(header, ['/register', '/signup']);

        if (login && register) {
            return commonAncestor(login, register, header);
        }

        const account = findAnchor(header, ['/account', '/dashboard']);
        const logout = header.querySelector(
            'form[action*="/logout"], a[href*="/logout"], button[data-logout]'
        );

        if (account && logout) {
            return commonAncestor(account, logout, header);
        }

        return null;
    };

    const findProfileMenu = () => {
        const header = publicHeader();
        if (!header) return null;
        return menuFromKnownClasses(header) || menuFromAuthLinks(header);
    };

    const makeRow = (toggle) => {
        const row = document.createElement('div');
        row.className = 'barama-profile-theme-row';
        row.setAttribute('data-barama-profile-theme-row', '');

        const copy = document.createElement('div');
        copy.className = 'barama-profile-theme-copy';
        copy.innerHTML =
            '<strong>Appearance</strong>' +
            '<small>Light / Dark mode</small>';

        toggle.classList.add('barama-profile-theme-toggle');
        toggle.setAttribute(RELOCATED, '1');

        row.append(copy, toggle);
        return row;
    };

    const revealFallback = () => {
        root.classList.add('barama-profile-theme-fallback');
        root.classList.remove('barama-profile-theme-ready');
    };

    const relocate = () => {
        if (document.querySelector('[data-barama-profile-theme-row]')) {
            root.classList.add('barama-profile-theme-ready');
            root.classList.remove('barama-profile-theme-fallback');
            return true;
        }

        const toggle = canonicalToggle();
        const menu = findProfileMenu();

        if (!toggle || !menu) {
            revealFallback();
            return false;
        }

        const row = makeRow(toggle);
        menu.appendChild(row);

        root.classList.add('barama-profile-theme-ready');
        root.classList.remove('barama-profile-theme-fallback');

        return true;
    };

    const start = () => {
        if (relocate()) return;

        const header = publicHeader();
        if (!header) {
            revealFallback();
            return;
        }

        let attempts = 0;
        const observer = new MutationObserver(() => {
            attempts += 1;
            if (relocate() || attempts >= 20) {
                observer.disconnect();
                if (attempts >= 20 && !document.querySelector('[data-barama-profile-theme-row]')) {
                    revealFallback();
                }
            }
        });

        observer.observe(header, { childList: true, subtree: true });

        window.setTimeout(() => {
            observer.disconnect();
            if (!document.querySelector('[data-barama-profile-theme-row]')) {
                revealFallback();
            }
        }, 3000);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();

/* BARAMA_RUNTIME_STYLE_DATA_BRIDGE_V1842 */
(() => {
    'use strict';

    const clampNumber = (value, min, max) => {
        const number = Number.parseFloat(String(value ?? '').trim());

        if (!Number.isFinite(number)) {
            return null;
        }

        return Math.min(max, Math.max(min, number));
    };

    const applyPercent = (element, dataKey, styleProperty) => {
        const value = clampNumber(element.dataset[dataKey], 0, 100);

        if (value === null) {
            return;
        }

        element.style[styleProperty] = `${value}%`;
    };

    const applyCustomProperty = (
        element,
        dataKey,
        propertyName,
        normalize
    ) => {
        const raw = String(element.dataset[dataKey] ?? '').trim();

        if (raw === '') {
            return;
        }

        const value = normalize ? normalize(raw) : raw;

        if (value === null || value === '') {
            return;
        }

        element.style.setProperty(propertyName, value);
    };

    const normalizeInteger = (min, max) => (raw) => {
        const value = Number.parseInt(raw, 10);

        if (!Number.isFinite(value)) {
            return null;
        }

        return String(Math.min(max, Math.max(min, value)));
    };

    const normalizeDecimal = (min, max) => (raw) => {
        const value = clampNumber(raw, min, max);

        return value === null ? null : String(value);
    };

    const normalizePercentProperty = (raw) => {
        const value = clampNumber(raw, 0, 100);

        return value === null ? null : `${value}%`;
    };

    const normalizeColor = (raw) => {
        const value = raw.trim();

        if (value === '' || value.length > 80) {
            return null;
        }

        if (
            window.CSS
            && typeof window.CSS.supports === 'function'
            && !window.CSS.supports('color', value)
        ) {
            return null;
        }

        return value;
    };

    const applyElement = (element) => {
        if (!(element instanceof Element)) {
            return;
        }

        if (element.hasAttribute('data-barama-width')) {
            applyPercent(element, 'baramaWidth', 'width');
        }

        if (element.hasAttribute('data-barama-left')) {
            applyPercent(element, 'baramaLeft', 'left');
        }

        if (element.hasAttribute('data-barama-marketplace-grid-columns')) {
            applyCustomProperty(
                element,
                'baramaMarketplaceGridColumns',
                '--marketplace-grid-columns',
                normalizeInteger(1, 12)
            );
        }

        if (element.hasAttribute('data-barama-marketplace-hero-overlay')) {
            applyCustomProperty(
                element,
                'baramaMarketplaceHeroOverlay',
                '--marketplace-hero-overlay',
                normalizeDecimal(0, 1)
            );
        }

        if (element.hasAttribute('data-barama-hero-overlay')) {
            applyCustomProperty(
                element,
                'baramaHeroOverlay',
                '--hero-overlay',
                normalizeDecimal(0, 1)
            );
        }

        if (element.hasAttribute('data-barama-invoice-accent')) {
            applyCustomProperty(
                element,
                'baramaInvoiceAccent',
                '--invoice-accent',
                normalizeColor
            );
        }

        if (element.hasAttribute('data-barama-readiness-score')) {
            applyCustomProperty(
                element,
                'baramaReadinessScore',
                '--readiness-score',
                normalizeInteger(0, 100)
            );
        }

        if (element.hasAttribute('data-barama-seo-health')) {
            applyCustomProperty(
                element,
                'baramaSeoHealth',
                '--seo-health',
                normalizePercentProperty
            );
        }
    };

    const selector = [
        '[data-barama-width]',
        '[data-barama-left]',
        '[data-barama-marketplace-grid-columns]',
        '[data-barama-marketplace-hero-overlay]',
        '[data-barama-hero-overlay]',
        '[data-barama-invoice-accent]',
        '[data-barama-readiness-score]',
        '[data-barama-seo-health]',
    ].join(',');

    const applyRuntimeStyles = (root = document) => {
        if (root instanceof Element && root.matches(selector)) {
            applyElement(root);
        }

        if (!root.querySelectorAll) {
            return;
        }

        root.querySelectorAll(selector).forEach(applyElement);
    };

    const start = () => {
        applyRuntimeStyles(document);

        if (typeof MutationObserver !== 'function') {
            return;
        }

        const attributeFilter = [
            'data-barama-width',
            'data-barama-left',
            'data-barama-marketplace-grid-columns',
            'data-barama-marketplace-hero-overlay',
            'data-barama-hero-overlay',
            'data-barama-invoice-accent',
            'data-barama-readiness-score',
            'data-barama-seo-health',
        ];

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes') {
                    applyElement(mutation.target);
                    return;
                }

                mutation.addedNodes.forEach((node) => {
                    if (node instanceof Element) {
                        applyRuntimeStyles(node);
                    }
                });
            });
        });

        observer.observe(document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter,
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    window.BaramaRuntimeStyles = Object.freeze({
        apply: applyRuntimeStyles,
    });
})();

};

/* ===== public-location-picker ===== */
modules["public-location-picker"]=function(__baramaScript){
/* BARAMA_PUBLIC_LOCATION_PICKER_V115 */
(() => {
  'use strict';
  const roots = Array.from(document.querySelectorAll('[data-public-location-picker]'));
  if (!roots.length) return;

  const requestJson = async (url) => {
    const response = await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
    const data = await response.json().catch(()=>({}));
    if (!response.ok || data.ok === false) throw new Error(data.message || `Location request failed (${response.status}).`);
    return data;
  };
  const flagFor = (code) => {
    const cc=String(code||'').toUpperCase();
    if(!/^[A-Z]{2}$/.test(cc)) return '🌐';
    return [...cc].map(c=>String.fromCodePoint(127397+c.charCodeAt(0))).join('');
  };
  const humanize = (value) => String(value||'Area').replace(/[_-]+/g,' ').replace(/\b\w/g,m=>m.toUpperCase());

  roots.forEach((root) => {
    if (root.dataset.publicLocationReady === '1') return;
    root.dataset.publicLocationReady='1';

    const endpoint=root.dataset.locationEndpoint||'/location-options';
    const initialValue=String(root.dataset.selectedLocation||'').trim();
    const emptyLabel=root.dataset.emptyLabel||'All locations';
    const trigger=root.querySelector('[data-public-location-trigger]');
    const quick=root.querySelector('[data-public-location-quick]');
    const quickList=root.querySelector('[data-public-location-country-list]');
    const quickSearch=root.querySelector('[data-public-location-country-search]');
    const advanced=root.querySelector('[data-public-location-advanced]');
    const close=root.querySelector('[data-public-location-close]');
    const done=root.querySelector('[data-public-location-done]');
    const clear=root.querySelector('[data-public-location-clear]');
    const countrySelect=root.querySelector('[data-public-location-country-select]');
    const levels=root.querySelector('[data-public-location-levels]');
    const value=root.querySelector('[data-public-location-value]');
    const label=root.querySelector('[data-public-location-label]');
    const status=root.querySelector('[data-public-location-status]');
    const form=root.closest('form');
    const advancedTrigger=form?.querySelector('[data-public-location-advanced-trigger]')||null;
    if(!trigger||!quick||!quickList||!advanced||!countrySelect||!levels||!value||!label) return;

    document.body.appendChild(quick);
    document.body.appendChild(advanced);

    let countries=[];
    let ordered=[];
    let activeCountry='';
    let currentCountryName='';
    let selection=null;
    let openPanel=null;
    let anchorEl=trigger;

    const setStatus=(message='',error=false)=>{if(!status)return;status.textContent=message;status.classList.toggle('is-error',error);};
    const setChoice=(nextValue,nextLabel)=>{value.value=String(nextValue||'');label.textContent=nextLabel||emptyLabel;};
    const findCountry=(code)=>countries.find(r=>String(r.country_code||'').toUpperCase()===String(code||'').toUpperCase())||null;
    const findCountryName=(code)=>String(findCountry(code)?.name||code||'');
    const sortCountries=(rows)=>{
      const list=Array.isArray(rows)?rows.slice():[];
      list.sort((a,b)=>{
        const ad=Number(a.is_default)===1?0:1, bd=Number(b.is_default)===1?0:1;
        if(ad!==bd)return ad-bd;
        return String(a.name||'').localeCompare(String(b.name||''));
      });
      return list;
    };
    const clearLevels=()=>{levels.innerHTML='';requestAnimationFrame(positionPanel);};

    const positionPanel=()=>{
      if(!openPanel||openPanel.hidden||!anchorEl)return;
      const mobile=window.matchMedia('(max-width:760px)').matches;
      if(mobile){Object.assign(openPanel.style,{left:'12px',right:'12px',top:'auto',bottom:'12px',width:'auto'});return;}
      const rect=anchorEl.getBoundingClientRect();
      const vw=document.documentElement.clientWidth||window.innerWidth;
      const vh=document.documentElement.clientHeight||window.innerHeight;
      const margin=12,gap=8;
      const width=Math.min(openPanel===quick?400:430,vw-margin*2);
      openPanel.style.right='auto';openPanel.style.bottom='auto';openPanel.style.width=`${width}px`;
      openPanel.style.left=`${Math.max(margin,Math.min(rect.left,vw-width-margin))}px`;
      const natural=Math.min(openPanel.scrollHeight||420,vh-margin*2);
      const below=vh-rect.bottom-margin-gap,above=rect.top-margin-gap;
      const useAbove=below<Math.min(natural,390)&&above>below;
      openPanel.style.top=useAbove?`${Math.max(margin,rect.top-gap-natural)}px`:`${Math.min(vh-natural-margin,rect.bottom+gap)}px`;
    };
    const shut=()=>{[quick,advanced].forEach(p=>p.hidden=true);root.classList.remove('is-open');trigger.setAttribute('aria-expanded','false');openPanel=null;};
    const open=(panel,anchor)=>{[quick,advanced].forEach(p=>{if(p!==panel)p.hidden=true;});openPanel=panel;anchorEl=anchor||trigger;panel.hidden=false;root.classList.add('is-open');if(panel===quick)trigger.setAttribute('aria-expanded','true');positionPanel();requestAnimationFrame(positionPanel);};

    const renderQuick=(filter='')=>{
      const needle=String(filter||'').trim().toLowerCase();
      quickList.innerHTML='';
      const add=(code,name,isDefault=false)=>{
        if(needle && !String(name).toLowerCase().includes(needle))return;
        const button=document.createElement('button');button.type='button';button.className='barama-public-location-picker__quick-item';button.dataset.countryCode=code;if(code===activeCountry)button.classList.add('is-active');
        const flag=document.createElement('span');flag.className='barama-public-location-picker__flag';flag.textContent=code?flagFor(code):'🌐';
        const text=document.createElement('span');text.className='barama-public-location-picker__country-name';text.textContent=name;button.append(flag,text);
        if(isDefault){const badge=document.createElement('span');badge.className='barama-public-location-picker__default';badge.textContent='Default';button.appendChild(badge);}
        if(code){const arrow=document.createElement('span');arrow.className='barama-public-location-picker__arrow';arrow.textContent='›';button.appendChild(arrow);}
        button.addEventListener('click',()=>{activeCountry=String(code||'').toUpperCase();currentCountryName=code?name:'';if(!code)setChoice('',emptyLabel);else setChoice(`country:${activeCountry}`,name);renderQuick(quickSearch?.value||'');shut();});
        quickList.appendChild(button);
      };
      add('',emptyLabel,false);
      ordered.forEach(r=>add(String(r.country_code||'').toUpperCase(),String(r.name||r.country_code||''),Number(r.is_default)===1));
    };

    const populateCountrySelect=()=>{
      countrySelect.innerHTML='';
      const all=document.createElement('option');all.value='';all.textContent=`🌐 ${emptyLabel}`;countrySelect.appendChild(all);
      ordered.forEach(r=>{const code=String(r.country_code||'').toUpperCase();const option=document.createElement('option');option.value=code;option.textContent=`${flagFor(code)} ${String(r.name||code)}${Number(r.is_default)===1?' — Default':''}`;countrySelect.appendChild(option);});
      countrySelect.value=activeCountry;
    };

    const typeLabel=(rows,depth)=>{const types=[...new Set(rows.map(r=>humanize(r.type)).filter(Boolean))];if(types.length===1)return types[0];if(depth===0)return 'State / Province / Region';if(depth===1)return 'District / County / Area';return `Local area ${depth+1}`;};
    const loadLevel=async(countryCode,parentId,depth,selectedId=0,parentLabel='')=>{
      const params=new URLSearchParams({country:countryCode});if(parentId)params.set('parent_id',String(parentId));
      const data=await requestJson(`${endpoint}?${params.toString()}`);const rows=Array.isArray(data.locations)?data.locations:[];if(!rows.length)return null;
      const field=document.createElement('label');field.className='barama-public-location-picker__field';field.dataset.publicLocationLevel=String(depth);
      const caption=document.createElement('span');caption.textContent=typeLabel(rows,depth);const select=document.createElement('select');
      const blank=document.createElement('option');blank.value='';blank.textContent=parentLabel?`All ${parentLabel}`:`All ${currentCountryName||'areas'}`;select.appendChild(blank);
      rows.forEach(row=>{const opt=document.createElement('option');opt.value=String(row.id||'');opt.textContent=String(row.name||'');opt.dataset.locationName=String(row.name||'');if(Number(row.id)===Number(selectedId))opt.selected=true;select.appendChild(opt);});
      field.append(caption,select);levels.appendChild(field);requestAnimationFrame(positionPanel);
      select.addEventListener('change',async()=>{Array.from(levels.querySelectorAll('[data-public-location-level]')).forEach(n=>{if(Number(n.dataset.publicLocationLevel)>depth)n.remove();});const id=Number(select.value||0);if(!id){if(parentId)setChoice(String(parentId),parentLabel||currentCountryName);else setChoice(`country:${countryCode}`,currentCountryName||countryCode);return;}const opt=select.options[select.selectedIndex];const name=opt?.dataset?.locationName||opt?.textContent||'Selected area';setChoice(String(id),name);try{await loadLevel(countryCode,id,depth+1,0,name);}catch(error){setStatus(error.message||'Unable to load the next area.',true);}});
      return select;
    };
    const advancedCountryChanged=async(code)=>{clearLevels();activeCountry=String(code||'').toUpperCase();currentCountryName=findCountryName(activeCountry);if(!activeCountry){setChoice('',emptyLabel);setStatus('Search all enabled locations.');renderQuick();return;}setChoice(`country:${activeCountry}`,currentCountryName);renderQuick();setStatus('Loading areas…');try{await loadLevel(activeCountry,null,0,0,currentCountryName);setStatus('Choose an area only if you need more detail.');}catch(error){setStatus(error.message||'Unable to load areas.',true);}};
    const openAdvanced=async()=>{populateCountrySelect();clearLevels();if(activeCountry){currentCountryName=findCountryName(activeCountry);try{if(selection&&Array.isArray(selection.path)&&selection.path.length){let parent=0,parentLabel=currentCountryName;for(let depth=0;depth<selection.path.length;depth++){const node=selection.path[depth];await loadLevel(activeCountry,parent||null,depth,Number(node.id),parentLabel);parent=Number(node.id);parentLabel=String(node.name||parentLabel);}if(parent)await loadLevel(activeCountry,parent,selection.path.length,0,parentLabel);}else{await loadLevel(activeCountry,null,0,0,currentCountryName);}}catch(error){setStatus(error.message||'Unable to load areas.',true);}}open(advanced,advancedTrigger||trigger);};

    const initialize=async()=>{try{const numeric=/^\d+$/.test(initialValue)?Number(initialValue):0;const token=/^country:([A-Za-z]{2})$/.exec(initialValue);const data=numeric>0?await requestJson(`${endpoint}?location_id=${encodeURIComponent(String(numeric))}`):await requestJson(endpoint);countries=Array.isArray(data.countries)?data.countries:[];ordered=sortCountries(countries);selection=data.selection&&typeof data.selection==='object'?data.selection:null;activeCountry=String(selection?.country_code||(token?token[1]:'')).toUpperCase();currentCountryName=findCountryName(activeCountry);if(numeric>0&&selection&&Array.isArray(selection.path)&&selection.path.length)setChoice(String(numeric),String(selection.path.at(-1)?.name||currentCountryName||emptyLabel));else if(activeCountry)setChoice(`country:${activeCountry}`,currentCountryName);else setChoice('',emptyLabel);renderQuick();populateCountrySelect();}catch(error){quickList.innerHTML='<div class="barama-public-location-picker__error">Unable to load locations.</div>';setStatus(error.message||'Unable to load locations.',true);}};

    trigger.addEventListener('click',(event)=>{event.preventDefault();event.stopPropagation();if(openPanel===quick&&!quick.hidden)shut();else{if(quickSearch)quickSearch.value='';renderQuick();open(quick,trigger);setTimeout(()=>quickSearch?.focus(),0);}});
    quickSearch?.addEventListener('input',()=>renderQuick(quickSearch.value));
    advancedTrigger?.addEventListener('click',(event)=>{event.preventDefault();event.stopPropagation();openAdvanced();});
    countrySelect.addEventListener('change',()=>advancedCountryChanged(countrySelect.value));
    close?.addEventListener('click',shut);done?.addEventListener('click',shut);
    clear?.addEventListener('click',()=>{selection=null;activeCountry='';currentCountryName='';countrySelect.value='';clearLevels();setChoice('',emptyLabel);renderQuick();setStatus('Search all enabled locations.');});
    [quick,advanced].forEach(panel=>panel.addEventListener('click',e=>e.stopPropagation()));
    document.addEventListener('click',(event)=>{if(openPanel&&!root.contains(event.target)&&!openPanel.contains(event.target)&&event.target!==advancedTrigger)shut();});
    document.addEventListener('keydown',(event)=>{if(event.key==='Escape'&&openPanel){shut();trigger.focus();}});
    window.addEventListener('resize',positionPanel,{passive:true});window.addEventListener('scroll',positionPanel,{passive:true,capture:true});
    if('ResizeObserver'in window){const ro=new ResizeObserver(positionPanel);ro.observe(quick);ro.observe(advanced);}
    initialize();
  });
})();

};

/* ===== notification-tray ===== */
modules["notification-tray"]=function(__baramaScript){
/* Barama v1.0.12 — scoped notification tray, no network requests. */
(() => {
  'use strict';
  const trays = Array.from(document.querySelectorAll('[data-notification-tray]'));
  if (!trays.length) return;
  function closeTray(tray) {
    const button = tray.querySelector('[data-notification-toggle]');
    const panel = tray.querySelector('[data-notification-panel]');
    if (!button || !panel) return;
    panel.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  }
  trays.forEach((tray) => {
    const button = tray.querySelector('[data-notification-toggle]');
    const panel = tray.querySelector('[data-notification-panel]');
    if (!button || !panel) return;
    button.addEventListener('click', () => {
      const shouldOpen = panel.hidden;
      trays.forEach(closeTray);
      panel.hidden = !shouldOpen;
      button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    });
  });
  document.addEventListener('pointerdown', (event) => {
    if (!event.target.closest('[data-notification-tray]')) trays.forEach(closeTray);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      trays.forEach(closeTray);
      document.activeElement?.blur?.();
    }
  });
})();

};

/* ===== public-header-inline ===== */
modules["public-header-inline"]=function(__baramaScript){
/* BARAMA_PUBLIC_HEADER_INLINE_V1121_JS */
(() => {
    'use strict';

    const LINK_SELECTOR = '.site-header .main-nav a';
    const LABEL_CLASS = 'barama-nav-label-v1121';

    function shouldWrapTextNode(node) {
        if (!node || node.nodeType !== Node.TEXT_NODE) return false;
        if (!node.nodeValue || !node.nodeValue.trim()) return false;
        const parent = node.parentElement;
        if (!parent) return false;
        if (parent.closest('svg, script, style, template')) return false;
        if (parent.classList.contains(LABEL_CLASS)) return false;
        return true;
    }

    function normalizeLink(link) {
        if (!(link instanceof Element)) return;

        const accessibleLabel = (link.getAttribute('aria-label') || link.textContent || '')
            .replace(/\s+/g, ' ')
            .trim();

        const walker = document.createTreeWalker(
            link,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode(node) {
                    return shouldWrapTextNode(node)
                        ? NodeFilter.FILTER_ACCEPT
                        : NodeFilter.FILTER_REJECT;
                }
            }
        );

        const textNodes = [];
        while (walker.nextNode()) textNodes.push(walker.currentNode);

        textNodes.forEach((node) => {
            const span = document.createElement('span');
            span.className = LABEL_CLASS;
            span.textContent = node.nodeValue;
            node.parentNode.replaceChild(span, node);
        });

        link.classList.add('barama-nav-icon-link-v1121');
        if (accessibleLabel && !link.getAttribute('aria-label')) {
            link.setAttribute('aria-label', accessibleLabel);
        }
    }

    function normalizeHeaderNavigation() {
        document.querySelectorAll(LINK_SELECTOR).forEach(normalizeLink);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', normalizeHeaderNavigation, { once: true });
    } else {
        normalizeHeaderNavigation();
    }
})();

};

/* ===== frontend-back-to-top ===== */
modules["frontend-back-to-top"]=function(__baramaScript){
/* BARAMA_FRONTEND_BACK_TO_TOP_V102 */
(function () {
    'use strict';

    function getScrollTop() {
        return Math.max(
            Number(window.pageYOffset || 0),
            Number(window.scrollY || 0),
            Number(document.documentElement && document.documentElement.scrollTop || 0),
            Number(document.body && document.body.scrollTop || 0)
        );
    }

    function getRevealThreshold() {
        var documentHeight = Math.max(
            document.documentElement ? document.documentElement.scrollHeight : 0,
            document.body ? document.body.scrollHeight : 0
        );
        var viewportHeight = Math.max(
            window.innerHeight || 0,
            document.documentElement ? document.documentElement.clientHeight : 0
        );
        var maxScroll = Math.max(0, documentHeight - viewportHeight);

        if (maxScroll <= 0) {
            return Number.POSITIVE_INFINITY;
        }

        return Math.min(180, Math.max(72, maxScroll * 0.12));
    }

    function init() {
        var button = document.querySelector('[data-back-to-top]');
        if (!button) {
            return;
        }

        var reducedMotion = window.matchMedia
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : null;
        var scheduled = false;

        function update() {
            scheduled = false;
            var visible = getScrollTop() > getRevealThreshold();

            button.setAttribute('data-visible', visible ? 'true' : 'false');
            button.setAttribute('aria-hidden', visible ? 'false' : 'true');
            button.tabIndex = visible ? 0 : -1;
        }

        function scheduleUpdate() {
            if (scheduled) {
                return;
            }

            scheduled = true;

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(update);
            } else {
                window.setTimeout(update, 16);
            }
        }

        button.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                left: 0,
                behavior: reducedMotion && reducedMotion.matches ? 'auto' : 'smooth'
            });
        });

        window.addEventListener('scroll', scheduleUpdate, { passive: true });
        window.addEventListener('resize', scheduleUpdate, { passive: true });

        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

};

/* ===== footer-back-to-top-clearance ===== */
modules["footer-back-to-top-clearance"]=function(__baramaScript){
/* Barama v1.0.6 — Footer-aware floating Back-to-Top button.
   Only adjusts a CSS variable on the existing button. Does not change
   social links, footer content, theme controls or scroll behavior. */
(function () {
    'use strict';

    function init() {
        var button = document.querySelector('[data-back-to-top]');
        var footer = document.querySelector('.site-footer__bottom');
        if (!button || !footer || !document.body.classList.contains('barama-public-surface')) return;

        var property = '--barama-footer-clearance';
        var scheduled = false;
        function overlaps(a, b, gap) {
            return a.left < b.right + gap && a.right > b.left - gap &&
                a.top < b.bottom + gap && a.bottom > b.top - gap;
        }
        function update() {
            scheduled = false;
            // Measure the normal button position on every scroll/resize;
            // measuring a previously displaced button causes oscillation.
            button.style.removeProperty(property);
            if (button.getAttribute('data-visible') !== 'true') return;
            var buttonRect = button.getBoundingClientRect();
            var links = footer.querySelectorAll('.site-footer__social-link, .site-footer__theme');
            var earliest = Infinity;
            var collision = false;
            for (var i = 0; i < links.length; i++) {
                var el = links[i];
                var rect = el.getBoundingClientRect();
                if (!rect.width || !rect.height || rect.bottom < 0 || rect.top > window.innerHeight) continue;
                if (overlaps(buttonRect, rect, 12)) collision = true;
                if (rect.top < earliest) earliest = rect.top;
            }
            if (!collision) return;
            // Clear the entire footer-bottom strip, including copyright,
            // rather than moving just a few pixels above one icon.
            var row = footer.getBoundingClientRect();
            var boundary = row.top > buttonRect.height + 25
                ? Math.min(row.top, earliest)
                : earliest;
            var neededBottom = Math.ceil(window.innerHeight - boundary + 16);
            if (window.innerHeight - neededBottom - buttonRect.height < 12) {
                // Extremely short viewports: keep the button clear of icons
                // by moving it above their row even if partially offscreen.
                neededBottom = Math.ceil(window.innerHeight - earliest + 12);
            }
            button.style.setProperty(property, neededBottom + 'px');
        }
        function schedule() {
            if (scheduled) return;
            scheduled = true;
            window.requestAnimationFrame(update);
        }
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
        if ('ResizeObserver' in window) {
            new ResizeObserver(schedule).observe(footer);
        }
        schedule();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

};

/* ===== right-click-protection ===== */
modules["right-click-protection"]=function(__baramaScript){
/* BARAMA_RIGHT_CLICK_PROTECTION_V134 */
(() => {
    'use strict';
    const root = document.documentElement;
    if (root.dataset.rightClickProtection !== '1') return;

    const editable = (target) => target instanceof Element
        && !!target.closest('input, textarea, select, [contenteditable="true"], pre, code');

    if (root.dataset.rightClickContextMenu === '1') {
        document.addEventListener('contextmenu', (event) => {
            if (!editable(event.target)) event.preventDefault();
        });
    }

    if (root.dataset.rightClickImageDrag === '1') {
        document.addEventListener('dragstart', (event) => {
            if (event.target instanceof HTMLImageElement) event.preventDefault();
        });
    }
})();

};

/* ===== barama-turnstile ===== */
modules["barama-turnstile"]=function(__baramaScript){
/* Barama first-party Turnstile loader: only enabled forms get a challenge. */
(() => {
  'use strict';
  const cfg = __baramaScript;
  if (!cfg) return;
  const key = cfg.dataset.sitekey || '';
  const forms = new Set((cfg.dataset.forms || '').split(',').filter(Boolean));
  const appearance = ['always','execute','interaction-only'].includes(cfg.dataset.appearance)
    ? cfg.dataset.appearance : 'always';
  if (!key || forms.size === 0) return;
  const map = new Map([['/login','login'],['/register','register'],
    ['/forgot-password','forgot_password'],['/contact/messages','contact'],['/inquiries','inquiry']]);
  const render = () => {
    if (!window.turnstile || typeof window.turnstile.render !== 'function') return;
    document.querySelectorAll('form[action]').forEach(form => {
      let path;
      try { path = new URL(form.getAttribute('action'), location.href).pathname; } catch (_) { return; }
      const action = map.get(path);
      if (!forms.has(action) || form.dataset.baramaTurnstileAttached === '1') return;
      form.dataset.baramaTurnstileAttached = '1';
      const holder = document.createElement('div');
      holder.className = 'barama-turnstile-widget';
      holder.setAttribute('aria-label','Bot verification');
      holder.style.margin = '14px 0';
      const submit = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submit) form.insertBefore(holder,submit); else form.appendChild(holder);
      window.turnstile.render(holder,{sitekey:key,action:action,appearance:appearance,'response-field':true});
    });
  };
  window.baramaTurnstileReady = () => {
    render();
    const observer = new MutationObserver(() => render());
    observer.observe(document.body, {childList:true, subtree:true});
  };
  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=baramaTurnstileReady';
  script.async = true;
  script.defer = true;
  document.head.appendChild(script);
})();

};

/* ===== contact-polish ===== */
modules["contact-polish"]=function(__baramaScript){
/* A21 consent hint. No interception of valid submissions, form values, or AJAX hooks. */
(function () {
  'use strict';
  function init() {
    var main = document.querySelector('.barama-a21-contact');
    if (!main) return;
    var forms = main.querySelectorAll('form[data-inquiry-form][data-contact-form]');
    forms.forEach(function (form) {
      var consent = form.querySelector('input[type="checkbox"][name="consent"][required]');
      var error = form.querySelector('#barama-a21-consent-error');
      if (!consent || !error) return; // Consent may be disabled by Admin.
      var showError = function () {
        consent.setAttribute('aria-invalid', 'true');
        error.hidden = false;
      };
      var clearError = function () {
        if (consent.checked) {
          consent.removeAttribute('aria-invalid');
          error.hidden = true;
        }
      };
      consent.addEventListener('invalid', function (event) {
        // Suppress native tooltip for this one checkbox only. All native
        // required-field constraints and existing submit handlers remain active.
        event.preventDefault();
        showError();
      });
      consent.addEventListener('change', clearError);
      consent.addEventListener('input', clearError);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once:true });
  } else {
    init();
  }
})();

};

/* ===== about-editor ===== */
modules["about-editor"]=function(__baramaScript){
/* Barama v1.0.0 canonical JavaScript; original independent IIFEs preserved. */

/* -- about-editor-v117a26.js -- */
/* Barama About A26: repeatable editor, exact field names, responsive actions. */
(function(){
  'use strict';
  const fields={
    values:[['title','Title',110,'text'],['description','Description',360,'textarea']],
    stats:[['value','Value',30,'text'],['label','Label',100,'text']],
    team:[['name','Name',120,'text'],['role','Role',120,'text'],['bio','Bio',500,'textarea'],['image_url','Image URL',1000,'url']]
  };
  function node(tag,cls,text){const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;}
  function rows(group){return Array.from(group.children).filter(el=>el.hasAttribute('data-a24-item'));}
  function action(name,text){const b=node('button','btn btn-outline-secondary',text);b.type='button';if(name==='remove')b.dataset.a24Remove='';else b.dataset.a24Move=name;b.setAttribute('aria-label',name==='remove'?'Remove item':'Move item '+name);return b;}
  function refresh(group){
    const list=rows(group),kind=group.dataset.kind,max=Number(group.dataset.max||8),form=group.closest('form');
    list.forEach((row,index)=>{
      const h=row.querySelector('[data-a24-item-title]');if(h)h.textContent=(kind==='team'?'Member':kind==='values'?'Value':'Stat')+' '+(index+1);
      row.querySelectorAll('[data-a24-field], [name^="items["]').forEach(field=>{
        const key=field.dataset.a24Field||(field.name.match(/\]\[([^\]]+)\]$/)||[])[1];
        if(key)field.name='items['+index+']['+key+']';
      });
      row.querySelectorAll('[data-a24-move]').forEach(btn=>{
        const direction=btn.dataset.a24Move;
        btn.disabled=direction==='up'?index===0:index===list.length-1;
      });
    });
    const add=form&&form.querySelector('[data-a24-add]');if(add)add.disabled=list.length>=max;
    const status=form&&form.querySelector('[data-a26-count]');if(status)status.textContent=list.length+' / '+max+' items';
  }
  function add(group){
    const kind=group.dataset.kind,limit=Number(group.dataset.max||8);
    if(!fields[kind]||rows(group).length>=limit)return;
    const row=node('section','barama-about-admin-a24__item');row.dataset.a24Item='';
    const head=node('div','barama-about-admin-a24__item-head');
    const h=node('strong','', 'New item');h.dataset.a24ItemTitle='';
    const actions=node('div','barama-about-a26__actions');
    actions.append(action('up','↑'),action('down','↓'),action('remove','Remove'));head.append(h,actions);row.append(head);
    fields[kind].forEach(([key,label,max,type])=>{
      const wrap=node('label'),span=node('span','',label),el=node(type==='textarea'?'textarea':'input','form-control');
      if(type==='textarea')el.rows=3;else el.type=type;
      el.maxLength=max;el.dataset.a24Field=key;
      wrap.append(span,el);row.append(wrap);
    });
    group.append(row);refresh(group);row.querySelector('input,textarea')?.focus();
  }
  function init(root){
    root.querySelectorAll('.barama-about-admin-a24 [data-a24-items]').forEach(group=>{
      if(!group.dataset.a26Ready){
        group.dataset.a26Ready='1';
        const form=group.closest('form'),addButton=form&&form.querySelector('[data-a24-add]');
        if(addButton){
          const count=node('span','barama-about-a26__count');count.dataset.a26Count='';count.setAttribute('aria-live','polite');addButton.insertAdjacentElement('afterend',count);
        }
      }
      refresh(group);
    });
  }
  document.addEventListener('click',event=>{
    const target=event.target;if(!(target instanceof Element))return;
    const btn=target.closest('[data-a24-add], [data-a24-remove], [data-a24-move]');if(!btn)return;
    const form=btn.closest('form'),group=btn.closest('[data-a24-items]')||(form&&form.querySelector('[data-a24-items]'));
    if(!group||!group.closest('.barama-about-admin-a24'))return;
    if(btn.matches('[data-a24-add]')){add(group);return;}
    const row=btn.closest('[data-a24-item]');if(!row)return;
    if(btn.matches('[data-a24-remove]')){row.remove();refresh(group);return;}
    if(btn.dataset.a24Move==='up'&&row.previousElementSibling)group.insertBefore(row,row.previousElementSibling);
    else if(btn.dataset.a24Move==='down'&&row.nextElementSibling)group.insertBefore(row.nextElementSibling,row);
    refresh(group);
  });
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>init(document),{once:true});
  else init(document);
  // The workspace can swap its content after an Ajax navigation. Initialize
  // newly inserted panels without duplicating click handlers.
  document.addEventListener('focusin',event=>{
    if(event.target instanceof Element&&event.target.closest('.barama-about-admin-a24'))init(document);
  });
})();


};

/* ===== marketplace-config ===== */
modules["marketplace-config"]=function(__baramaScript){
/* BARAMA_MARKETPLACE_CONFIG_V109 */
(() => {
    'use strict';

    const form = document.querySelector('[data-marketplace-config-form-v109]');
    if (!(form instanceof HTMLFormElement)) return;

    const dialog = form.closest('dialog');
    const submit = form.querySelector('[data-marketplace-config-submit]');
    const backgroundType = form.querySelector('[data-hero-background-type]');

    let toastTimer = null;

    const getToast = () => {
        let toast = document.querySelector('[data-marketplace-config-toast]');
        if (toast) return toast;

        toast = document.createElement('div');
        toast.className = 'barama-config-toast';
        toast.setAttribute('data-marketplace-config-toast', '');
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        document.body.appendChild(toast);
        return toast;
    };

    const showToast = (message, type = 'success') => {
        const toast = getToast();
        window.clearTimeout(toastTimer);
        toast.classList.remove('is-success', 'is-error', 'is-visible');
        toast.textContent = message;
        toast.classList.add(type === 'error' ? 'is-error' : 'is-success');
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        toastTimer = window.setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 2600);
    };

    const updatePanels = () => {
        if (!(backgroundType instanceof HTMLSelectElement)) return;
        form.querySelectorAll('[data-hero-background-panel]').forEach((panel) => {
            panel.hidden = panel.getAttribute('data-hero-background-panel') !== backgroundType.value;
        });
    };

    backgroundType?.addEventListener('change', updatePanels);
    updatePanels();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const title = form.querySelector('[name="hero_title"]');
        const description = form.querySelector('[name="hero_description"]');

        if (title instanceof HTMLInputElement && title.value.trim() === '') {
            showToast('Hero headline is required.', 'error');
            title.focus();
            return;
        }

        if (description instanceof HTMLTextAreaElement && description.value.trim() === '') {
            showToast('Hero description is required.', 'error');
            description.focus();
            return;
        }

        const data = new FormData(form);
        const type = String(data.get('background_type') || 'plain');
        let source = String(data.get('image_source') || 'auto');

        if (type === 'image') {
            const url = String(data.get('image_url') || '').trim();
            const mediaUuid = String(data.get('image_media_uuid') || '').trim();
            const imageFile = data.get('hero_image_file');
            const hasUpload = imageFile instanceof File && imageFile.size > 0;

            if (hasUpload) {
                source = 'media';
                data.set('image_source', 'media');
            } else if (
                source === 'auto'
                || (source === 'url' && url === '')
                || (source === 'media' && mediaUuid === '')
            ) {
                source = 'auto';
                data.set('image_source', 'auto');
                data.delete('image_url');
                data.delete('image_media_uuid');
                data.delete('hero_image_file');
            } else if (source === 'url') {
                data.delete('image_media_uuid');
                data.delete('hero_image_file');
            } else if (source === 'media') {
                data.delete('image_url');
            }
        } else {
            data.delete('image_source');
            data.delete('image_url');
            data.delete('image_media_uuid');
            data.delete('hero_image_file');
        }

        if (type !== 'slider') {
            data.delete('slider_existing[]');
            data.delete('slider_remove[]');
            data.delete('slider_urls');
            data.delete('hero_slider_files[]');
        }

        if (type !== 'video') {
            data.delete('video_source');
            data.delete('video_url');
            data.delete('video_media_uuid');
            data.delete('youtube_url');
            data.delete('hero_video_file');
        }

        if (submit instanceof HTMLButtonElement) {
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Barama-Marketplace-Config': 'v1.0.9'
                }
            });

            const responseText = await response.text();
            let payload = null;

            if (responseText.trim() !== '') {
                try {
                    payload = JSON.parse(responseText);
                } catch (_) {
                    payload = null;
                }
            }

            if (!response.ok || payload?.ok !== true) {
                const fallback = response.status === 419
                    ? 'Your session expired. Refresh the page and try again.'
                    : 'Configuration could not be saved.';
                throw new Error(payload?.message || fallback);
            }

            if (dialog instanceof HTMLDialogElement && dialog.open) {
                dialog.close();
            }

            showToast(payload?.data?.message || 'Configuration saved.', 'success');

            window.setTimeout(() => {
                window.location.reload();
            }, 850);
        } catch (error) {
            showToast(
                error instanceof Error ? error.message : 'Configuration could not be saved.',
                'error'
            );
        } finally {
            if (submit instanceof HTMLButtonElement) {
                submit.disabled = false;
                submit.removeAttribute('aria-busy');
            }
        }
    }, true);
})();

};

/* ===== location-provider ===== */
modules["location-provider"]=function(__baramaScript){
/* Barama A7: authenticated, temporary Mapbox suggestions. Never write provider result into Barama locations. */
(() => {
  'use strict';
  document.querySelectorAll('[data-location-mapbox]').forEach((root) => {
    if (root.dataset.mapboxBound === '1') return;
    root.dataset.mapboxBound = '1';
    const input = root.querySelector('[data-mapbox-q]');
    const button = root.querySelector('[data-mapbox-search]');
    const results = root.querySelector('[data-mapbox-results]');
    const cascade = root.closest('[data-location-cascade]');
    const country = cascade?.querySelector('[data-location-country]');
    if (!input || !button || !results || !country) return;
    const text = (message) => {
      results.replaceChildren();
      const small = document.createElement('small');
      small.textContent = message;
      results.appendChild(small);
    };
    const search = async () => {
      const q = input.value.trim();
      if (q.length < 3 || q.length > 120) { text('Enter at least 3 characters.'); return; }
      const params = new URLSearchParams({q});
      if (country.value) params.set('country', country.value);
      button.disabled = true;
      text('Searching Mapbox…');
      try {
        const response = await fetch(`${root.dataset.searchEndpoint}?${params}`, {
          credentials:'same-origin', headers:{'Accept':'application/json'}
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Location search is unavailable.');
        results.replaceChildren();
        if (!payload.results?.length) { text('No Mapbox results found. Select a Barama location below.'); return; }
        payload.results.forEach((item) => {
          const option = document.createElement('button');
          option.type = 'button';
          option.textContent = item.label;
          option.title = 'Select this country in Barama. Continue choosing the matching administrative area.';
          option.addEventListener('click', () => {
            const code = item.country_code;
            const available = [...country.options].some(o => o.value === code);
            if (!code || !available) {
              text('This place is outside enabled countries. Ask an administrator to change country scope.');
              return;
            }
            country.value = code;
            country.dispatchEvent(new Event('change', {bubbles:true}));
            text(`Mapbox place: ${item.label}. Now select the Barama administrative area below; no Mapbox data was saved.`);
          });
          results.appendChild(option);
        });
      } catch (error) { text(error instanceof Error ? error.message : 'Unable to search Mapbox.'); }
      finally { button.disabled = false; }
    };
    button.addEventListener('click', search);
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') { event.preventDefault(); search(); }
    });
  });
})();

};

/* ===== page-builder ===== */
modules["page-builder"]=function(__baramaScript){
/* BARAMA_PAGE_BUILDER_V120 */
(() => {
    'use strict';

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const iconState = (button, active) => {
        if (!(button instanceof HTMLElement)) return;
        button.classList.toggle('is-active', Boolean(active));
    };

    const init = () => {
        const builder = qs('[data-page-builder]');
        if (!(builder instanceof HTMLElement)) return;

        const form = qs('[data-page-builder-form]', builder);
        const editor = qs('[data-page-editor-surface]', builder);
        const hidden = qs('[data-page-editor-value]', builder);
        const title = qs('[data-page-title]', builder);
        const slug = qs('[data-page-slug]', builder);
        const fullUrl = qs('[data-page-full-url]', builder);
        const copyButton = qs('[data-copy-page-url]', builder);
        const wordCount = qs('[data-page-word-count]', builder);
        const toast = qs('[data-page-builder-toast]', builder);
        const mode = builder.dataset.mode || 'edit';
        const baseUrl = builder.dataset.baseUrl || '';
        let slugTouched = String(slug?.value || '').trim() !== '';

        if (!(form instanceof HTMLFormElement)
            || !(editor instanceof HTMLElement)
            || !(hidden instanceof HTMLTextAreaElement)
            || !(title instanceof HTMLInputElement)
            || !(slug instanceof HTMLInputElement)) {
            return;
        }

        try {
            document.execCommand('defaultParagraphSeparator', false, 'p');
        } catch (_) {
            // Browser may not expose this command; sanitizer still normalizes blocks.
        }

        const showToast = (message, error = false) => {
            if (!(toast instanceof HTMLElement)) return;
            toast.textContent = message || (error ? 'The request could not be completed.' : 'Saved.');
            toast.classList.toggle('is-error', error);
            toast.classList.remove('is-visible');
            window.requestAnimationFrame(() => toast.classList.add('is-visible'));
            window.setTimeout(() => toast.classList.remove('is-visible'), 2800);
        };

        const slugify = (value) => String(value || '')
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-')
            .slice(0, 160);

        const updateUrl = () => {
            const value = slugify(slug.value);
            if (slug.value !== value && slug.value.trim() !== '') {
                slug.value = value;
            }

            const url = baseUrl.replace(/\/$/, '') + '/' + value;
            if (fullUrl instanceof HTMLElement) {
                fullUrl.textContent = value ? url : baseUrl.replace(/\/$/, '') + '/…';
                fullUrl.dataset.copyValue = value ? url : '';
            }
        };

        const syncEditor = () => {
            hidden.value = editor.innerHTML.trim();
            const text = (editor.innerText || '').trim();
            const words = text === '' ? 0 : text.split(/\s+/).filter(Boolean).length;
            if (wordCount instanceof HTMLElement) {
                wordCount.textContent = String(words) + (words === 1 ? ' word' : ' words');
            }
        };

        const block = () => {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return null;
            let node = selection.anchorNode;
            if (node && node.nodeType === Node.TEXT_NODE) node = node.parentElement;
            if (!(node instanceof HTMLElement)) return null;
            return node.closest('p,h2,h3,h4,blockquote,li,pre') || node;
        };

        const exec = (command, value = null) => {
            editor.focus();
            document.execCommand(command, false, value);
            syncEditor();
        };

        qsa('[data-editor-command]', builder).forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                const command = button.dataset.editorCommand || '';
                if (!command) return;

                if (command === 'createLink') {
                    const href = window.prompt('Enter link URL');
                    if (href && href.trim()) exec('createLink', href.trim());
                    return;
                }

                if (command === 'insertImage') {
                    const src = window.prompt('Enter image URL');
                    if (src && /^https?:\/\//i.test(src.trim())) exec('insertImage', src.trim());
                    return;
                }

                if (command === 'insertHorizontalRule') {
                    exec('insertHorizontalRule');
                    return;
                }

                exec(command, button.dataset.editorValue || null);
            });
        });

        qsa('[data-editor-format]', builder).forEach((select) => {
            select.addEventListener('change', () => {
                const value = select.value || 'p';
                exec('formatBlock', value);
                select.value = '';
            });
        });

        qsa('[data-editor-align]', builder).forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                const target = block();
                if (!(target instanceof HTMLElement)) return;

                const align = button.dataset.editorAlign || 'left';
                if (align === 'left') {
                    target.removeAttribute('data-align');
                    target.style.textAlign = '';
                } else {
                    target.setAttribute('data-align', align);
                    target.style.textAlign = align;
                }

                syncEditor();
            });
        });

        document.addEventListener('selectionchange', () => {
            if (!editor.contains(document.activeElement) && !editor.contains(window.getSelection()?.anchorNode || null)) {
                return;
            }

            qsa('[data-editor-command]', builder).forEach((button) => {
                const command = button.dataset.editorCommand || '';
                if (!['bold','italic','underline','strikeThrough','insertUnorderedList','insertOrderedList','subscript','superscript'].includes(command)) {
                    return;
                }

                try {
                    iconState(button, document.queryCommandState(command));
                } catch (_) {
                    iconState(button, false);
                }
            });
        });

        title.addEventListener('input', () => {
            if (!slugTouched || slug.value.trim() === '') {
                slug.value = slugify(title.value);
                slugTouched = false;
                updateUrl();
            }
        });

        slug.addEventListener('input', () => {
            slugTouched = true;
            updateUrl();
        });

        copyButton?.addEventListener('click', async () => {
            const value = fullUrl?.dataset.copyValue || '';
            if (!value) {
                showToast('Add a page slug before copying the URL.', true);
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
                showToast('Full page URL copied.');
            } catch (_) {
                const temp = document.createElement('textarea');
                temp.value = value;
                temp.style.position = 'fixed';
                temp.style.opacity = '0';
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                temp.remove();
                showToast('Full page URL copied.');
            }
        });

        editor.addEventListener('input', syncEditor);

        qsa('[data-seo-count-source]', builder).forEach((field) => {
            const targetId = field.dataset.seoCountTarget;
            const target = targetId ? qs('#' + CSS.escape(targetId), builder) : null;
            const update = () => {
                if (target instanceof HTMLElement) {
                    target.textContent = String(field.value.length) + ' / ' + String(field.maxLength || '');
                }
            };
            field.addEventListener('input', update);
            update();
        });

        const submitJsonForm = async (targetForm) => {
            const button = qs('[type="submit"]', targetForm);
            const previous = button instanceof HTMLButtonElement ? button.textContent : '';

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
                button.textContent = 'Saving…';
            }

            try {
                const response = await fetch(targetForm.action, {
                    method: targetForm.method || 'POST',
                    body: new FormData(targetForm),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || payload.ok !== true) {
                    throw new Error(payload.message || 'The request could not be completed.');
                }

                return payload.data || {};
            } finally {
                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                    button.textContent = previous;
                }
            }
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            syncEditor();

            try {
                const data = await submitJsonForm(form);
                showToast(mode === 'create' ? 'Page draft created.' : 'Page saved successfully.');

                if (mode === 'create' && data.page_id) {
                    window.setTimeout(() => {
                        window.location.href = baseUrl.replace(/\/$/, '') + '/dashboard/pages/' + encodeURIComponent(String(data.page_id)) + '/edit?created=1';
                    }, 420);
                }
            } catch (error) {
                showToast(error instanceof Error ? error.message : 'The request could not be completed.', true);
            }
        });

        qsa('[data-page-status-form]', builder).forEach((statusForm) => {
            statusForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                try {
                    await submitJsonForm(statusForm);
                    showToast('Page status updated.');
                    window.setTimeout(() => window.location.reload(), 420);
                } catch (error) {
                    showToast(error instanceof Error ? error.message : 'The request could not be completed.', true);
                }
            });
        });

        updateUrl();
        syncEditor();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

};

const seen=new Set();
for(const marker of document.querySelectorAll('script[type="application/x-barama-module"][data-barama-module]')){
  const key=marker.getAttribute('data-barama-module');
  if(seen.has(key)||!Object.prototype.hasOwnProperty.call(modules,key))continue;
  seen.add(key);
  try{modules[key](marker);}catch(error){console.error('[Barama asset module]',key,error);}
}
})();
