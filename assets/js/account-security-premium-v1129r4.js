/* BARAMA_ACCOUNT_SECURITY_PREMIUM_TABLE_V1129_R4 */
(() => {
    'use strict';

    const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    if (location.pathname.replace(/\/+$/, '') !== '/account/settings/security') return;

    const SPECS = [
        ['Password & sessions','Credential and authenticated-session protection','Change the account password and invalidate other authenticated sessions in one secured workflow.','Protected','<path d="M6 10V8a6 6 0 0 1 12 0v2"></path><rect x="5" y="10" width="14" height="10" rx="2"></rect>'],
        ['Email & verification','Primary sign-in identity and verification','Change the primary email only after password confirmation and a secure verification workflow.','Verified','<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path>'],
        ['Account access','Account status, roles and verification','Review the current account state, last sign-in, account access and verification operations.','Active','<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>'],
        ['Security monitoring','Audit trail and security mail operations','Open the real audit trail and mail-delivery logs used for security and verification operations.','Available','<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="m9 12 2 2 4-4"></path>'],
        ['Change passcode','6-digit approval before workspace changes','When enabled, every authenticated Account, Vendor/Developer, or Admin change requires a single-use 6-digit approval.','Enabled','<circle cx="7.5" cy="15.5" r="3.5"></circle><path d="m10 13 9-9"></path><path d="m15 4 2 2"></path><path d="m18 7 2 2"></path>'],
        ['Multi-factor authentication','Authenticator app and recovery-code protection','Require a rotating TOTP authenticator code after password sign-in, with single-use recovery codes for account recovery.','Not configured','<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="M9 12h6"></path><path d="M12 9v6"></path>']
    ];

    const configureControls = (root = document) => Array.from(
        root.querySelectorAll('button,a,input[type="button"],input[type="submit"]')
    ).filter((el) => {
        const value = el instanceof HTMLInputElement ? el.value : el.textContent;
        return normalize(value).toLowerCase() === 'configure';
    });

    const countConfigure = (node) => node ? configureControls(node).length : 0;

    const highestSingleActionRow = (action, boundary) => {
        let node = action;
        let lastSingle = action;

        while (node.parentElement && node.parentElement !== boundary && node.parentElement !== document.body) {
            const parent = node.parentElement;
            const count = countConfigure(parent);

            if (count === 1) {
                lastSingle = parent;
                node = parent;
                continue;
            }
            break;
        }

        return lastSingle;
    };

    const statusFromRow = (row, fallback) => {
        const text = normalize(row.textContent).toLowerCase();
        const statuses = [
            'not configured','protected','verified','active','available','enabled',
            'disabled','configured','action required','recommended'
        ];
        const found = statuses.find((status) => text.includes(status));
        if (!found) return fallback;
        return found.replace(/\b\w/g, (char) => char.toUpperCase())
            .replace('Not Configured', 'Not configured');
    };

    const make = (tag, cls, text) => {
        const el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text !== undefined) el.textContent = text;
        return el;
    };

    const hideLegacyLabels = (page, shell) => {
        const labels = new Set(['live controls','feature','status','protection','actions']);
        Array.from(page.querySelectorAll('div,span,p,strong,b,h3,h4,h5,h6')).forEach((el) => {
            if (shell.contains(el)) return;
            if (labels.has(normalize(el.textContent).toLowerCase())) {
                el.classList.add('barama-security-r4-hide');
            }
        });
    };

    const init = () => {
        const page = document.querySelector('.account-page--security-v1129');
        if (!page || page.dataset.securityPremiumR4 === 'ready') return;

        const actions = configureControls(page).slice(0, 6);
        if (actions.length !== 6) {
            page.dataset.securityPremiumR4 = `configure-count-${actions.length}`;
            return;
        }

        const rows = actions.map((action) => highestSingleActionRow(action, page));
        if (new Set(rows).size !== 6) {
            page.dataset.securityPremiumR4 = 'row-uniqueness-failed';
            return;
        }

        const statuses = rows.map((row, index) => statusFromRow(row, SPECS[index][3]));

        const shell = make('section', 'barama-security-r4-shell');
        shell.setAttribute('data-barama-security-r4', 'table');

        const header = make('div', 'barama-security-r4-header');
        ['Feature','Status','Protection','Actions'].forEach((label) => {
            header.appendChild(make('div', 'barama-security-r4-head', label));
        });
        shell.appendChild(header);

        const body = make('div', 'barama-security-r4-body');

        SPECS.forEach((spec, index) => {
            const [name, summary, protection, fallbackStatus, icon] = spec;
            const row = make('div', 'barama-security-r4-row');

            const feature = make('div', 'barama-security-r4-cell barama-security-r4-feature');
            const iconWrap = make('span', 'barama-security-r4-icon');
            iconWrap.setAttribute('aria-hidden', 'true');
            iconWrap.innerHTML = `<svg viewBox="0 0 24 24">${icon}</svg>`;
            const copy = make('span', 'barama-security-r4-copy');
            copy.appendChild(make('strong', '', name));
            copy.appendChild(make('small', '', summary));
            feature.append(iconWrap, copy);

            const statusCell = make('div', 'barama-security-r4-cell barama-security-r4-status');
            const status = statuses[index] || fallbackStatus;
            const pill = make(
                'span',
                'barama-security-r4-pill' + (status.toLowerCase().includes('not configured') ? ' is-warning' : ''),
                status
            );
            statusCell.appendChild(pill);

            const protectionCell = make('div', 'barama-security-r4-cell barama-security-r4-protection', protection);

            const actionCell = make('div', 'barama-security-r4-cell barama-security-r4-action');
            const action = actions[index];
            const movable = action.closest('form') || action;
            actionCell.appendChild(movable);

            row.append(feature, statusCell, protectionCell, actionCell);
            body.appendChild(row);
        });

        shell.appendChild(body);

        // Place the new premium table exactly where the first legacy row began.
        const firstRow = rows[0];
        firstRow.parentElement.insertBefore(shell, firstRow);

        // Hide each old row independently; no common-grid assumption.
        rows.forEach((row) => {
            row.classList.add('barama-security-r4-hide');
            row.setAttribute('aria-hidden', 'true');
        });

        hideLegacyLabels(page, shell);

        page.dataset.securityPremiumR4 = 'ready';
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
