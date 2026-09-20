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
    const licenseMethods = document.querySelectorAll('[data-license-method]');
    const validateButton = document.querySelector('[data-license-validate]');
    const continueButton = document.querySelector('[data-license-continue]');
    const licenseStatus = document.querySelector('[data-license-status]');
    const termsModal = document.querySelector('[data-installer-terms-modal]');
    let licenseValidated = installer.getAttribute('data-license-validated') === '1';

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
            validateButton.disabled = !(hasCode && accepted);
        }

        if (continueButton) {
            continueButton.disabled = !(licenseValidated && accepted);
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

    licenseMethods.forEach((method) => {
        method.addEventListener('change', () => {
            document.querySelectorAll('.installer-license-method').forEach((option) => {
                option.classList.toggle(
                    'is-selected',
                    option.querySelector('[data-license-method]')?.checked === true
                );
            });

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
    });

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
        document.querySelector('[data-open-terms]')?.focus();
    };

    document.querySelector('[data-open-terms]')?.addEventListener('click', openTerms);
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

            installer.setAttribute('data-terms-reviewed', '1');

            if (agreement) {
                agreement.disabled = false;
                agreement.focus();
            }

            closeTerms();
            showNotice(
                payload?.data?.message || 'Terms reviewed. You may now accept the agreement.',
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

    document.querySelector('[data-installer-final-form]')?.addEventListener('submit', (event) => {
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
