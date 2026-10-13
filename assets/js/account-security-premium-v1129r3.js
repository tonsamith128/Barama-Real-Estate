/* BARAMA_ACCOUNT_SECURITY_PREMIUM_TABLE_V1129_R3 */
(() => {
    'use strict';

    const norm = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    const pagePath = location.pathname.replace(/\/+$/, '');
    if (pagePath !== '/account/settings/security') return;

    const SPECS = [
        {
            name: 'Password & sessions',
            summary: 'Credential and authenticated-session protection',
            protection: 'Change the account password and invalidate other authenticated sessions in one secured workflow.',
            status: ['Protected'],
            icon: '<path d="M6 10V8a6 6 0 0 1 12 0v2"></path><rect x="5" y="10" width="14" height="10" rx="2"></rect>'
        },
        {
            name: 'Email & verification',
            summary: 'Primary sign-in identity and verification',
            protection: 'Change the primary email only after password confirmation and a secure verification workflow.',
            status: ['Verified'],
            icon: '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path>'
        },
        {
            name: 'Account access',
            summary: 'Account status, roles and verification',
            protection: 'Review the current account state, last sign-in, account access and verification operations.',
            status: ['Active'],
            icon: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>'
        },
        {
            name: 'Security monitoring',
            summary: 'Audit trail and security mail operations',
            protection: 'Open the real audit trail and mail-delivery logs used for security and verification operations.',
            status: ['Available'],
            icon: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="m9 12 2 2 4-4"></path>'
        },
        {
            name: 'Change passcode',
            summary: '6-digit approval before workspace changes',
            protection: 'When enabled, every authenticated Account, Vendor/Developer, or Admin change requires a single-use 6-digit approval.',
            status: ['Enabled'],
            icon: '<circle cx="7.5" cy="15.5" r="3.5"></circle><path d="m10 13 9-9"></path><path d="m15 4 2 2"></path><path d="m18 7 2 2"></path>'
        },
        {
            name: 'Multi-factor authentication',
            summary: 'Authenticator app and recovery-code protection',
            protection: 'Require a rotating TOTP authenticator code after password sign-in, with single-use recovery codes for account recovery.',
            status: ['Not configured'],
            icon: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="M9 12h6"></path><path d="M12 9v6"></path>'
        }
    ];

    const controls = () => Array.from(document.querySelectorAll(
        'button,a,input[type="button"],input[type="submit"]'
    )).filter((el) => {
        const text = el instanceof HTMLInputElement ? el.value : el.textContent;
        return norm(text).toLowerCase() === 'configure';
    });

    const findSection = () => {
        const candidates = Array.from(document.querySelectorAll('h1,h2,h3,h4,div,section'));
        const heading = candidates.find((el) => norm(el.textContent).startsWith('Security features'));
        if (!heading) return document.querySelector('.account-page--security-v1129');

        let node = heading;
        while (node && node !== document.body) {
            if (controls().filter((btn) => node.contains(btn)).length >= 6) return node;
            node = node.parentElement;
        }
        return document.querySelector('.account-page--security-v1129');
    };

    const detectStatus = (area, spec) => {
        const whole = norm(area?.textContent || '');
        for (const status of spec.status) {
            if (whole.toLowerCase().includes(status.toLowerCase())) return status;
        }
        return spec.status[0];
    };

    const create = (tag, cls, html = '') => {
        const el = document.createElement(tag);
        if (cls) el.className = cls;
        if (html) el.innerHTML = html;
        return el;
    };

    const init = () => {
        const page = document.querySelector('.account-page--security-v1129');
        if (!page || page.dataset.securityPremiumR3 === 'ready') return;

        const actions = controls();
        if (actions.length < 6) {
            page.dataset.securityPremiumR3 = 'configure-count-failed';
            return;
        }

        const actionSet = actions.slice(0, 6);
        const area = findSection();
        if (!area) {
            page.dataset.securityPremiumR3 = 'area-failed';
            return;
        }

        // Identify the smallest common container that contains all six Configure controls.
        let grid = actionSet[0].parentElement;
        while (grid && !actionSet.every((btn) => grid.contains(btn))) {
            grid = grid.parentElement;
        }
        if (!grid || grid === document.body || grid === page) {
            page.dataset.securityPremiumR3 = 'grid-failed';
            return;
        }

        const shell = create('div', 'barama-security-r3-shell');
        shell.setAttribute('data-barama-security-r3', 'table');

        const header = create('div', 'barama-security-r3-header');
        ['Feature', 'Status', 'Protection', 'Actions'].forEach((label) => {
            const cell = create('div', 'barama-security-r3-head-cell');
            cell.textContent = label;
            header.appendChild(cell);
        });
        shell.appendChild(header);

        const body = create('div', 'barama-security-r3-body');

        SPECS.forEach((spec, index) => {
            const row = create('div', 'barama-security-r3-row');

            const feature = create('div', 'barama-security-r3-cell barama-security-r3-feature');
            feature.innerHTML = [
                '<span class="barama-security-r3-icon" aria-hidden="true">',
                '<svg viewBox="0 0 24 24">', spec.icon, '</svg>',
                '</span>',
                '<span class="barama-security-r3-feature-copy">',
                '<strong></strong><small></small>',
                '</span>'
            ].join('');
            feature.querySelector('strong').textContent = spec.name;
            feature.querySelector('small').textContent = spec.summary;

            const statusCell = create('div', 'barama-security-r3-cell barama-security-r3-status');
            const status = detectStatus(area, spec);
            const pill = create(
                'span',
                'barama-security-r3-pill' + (status.toLowerCase().includes('not configured') ? ' is-warning' : '')
            );
            pill.textContent = status;
            statusCell.appendChild(pill);

            const protection = create('div', 'barama-security-r3-cell barama-security-r3-protection');
            protection.textContent = spec.protection;

            const actionCell = create('div', 'barama-security-r3-cell barama-security-r3-action');
            const action = actionSet[index];

            // Keep the original control itself. If it is nested in a form, move the form.
            const movable = action.closest('form') || action;
            actionCell.appendChild(movable);

            row.append(feature, statusCell, protection, actionCell);
            body.appendChild(row);
        });

        shell.appendChild(body);

        // Insert directly before the old grid, then hide the old visual structure.
        grid.parentElement.insertBefore(shell, grid);
        grid.classList.add('barama-security-r3-old-grid');
        grid.setAttribute('aria-hidden', 'true');

        // Hide legacy column labels / Live controls only when they are outside the hidden grid.
        ['Feature', 'Status', 'Protection', 'Actions'].forEach((label) => {
            Array.from(area.querySelectorAll('div,span,p,strong,b,h3,h4')).forEach((el) => {
                if (norm(el.textContent) === label && !shell.contains(el) && !grid.contains(el)) {
                    el.style.display = 'none';
                }
            });
        });

        const liveControls = Array.from(area.querySelectorAll('div,span,p,strong,b,h3,h4'))
            .find((el) => norm(el.textContent) === 'Live controls');
        if (liveControls && !shell.contains(liveControls) && !grid.contains(liveControls)) {
            liveControls.style.display = 'none';
        }

        page.dataset.securityPremiumR3 = 'ready';
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
