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
                    submitter?.formAction || form.action,
                    {
                    method: submitter?.formMethod || form.method || 'POST',
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

        input.addEventListener('change', () => {
            value.value = input.checked ? '1' : '0';

            if (label) {
                label.textContent = input.checked ? 'ON' : 'OFF';
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
