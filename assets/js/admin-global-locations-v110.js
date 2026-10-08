/* BARAMA_GLOBAL_LOCATIONS_V110 */
(() => {
    'use strict';

    const root = document.querySelector('[data-global-location-manager]');
    if (!root) return;

    const search = root.querySelector('[data-country-search]');
    const status = root.querySelector('[data-country-status-filter]');
    const rows = Array.from(root.querySelectorAll('[data-country-row]'));
    const empty = root.querySelector('[data-country-empty]');

    const applyFilter = () => {
        const query = String(search?.value || '').trim().toLowerCase();
        const wantedStatus = String(status?.value || 'all');
        let visible = 0;

        rows.forEach((row) => {
            const haystack = String(row.dataset.countryName || '');
            const rowStatus = String(row.dataset.countryStatus || 'disabled');
            const matchesSearch = query === '' || haystack.includes(query);
            const matchesStatus = wantedStatus === 'all' || wantedStatus === rowStatus;
            const show = matchesSearch && matchesStatus;
            row.hidden = !show;
            if (show) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
    };

    search?.addEventListener('input', applyFilter);
    status?.addEventListener('change', applyFilter);

    root.querySelectorAll('.barama-location-choice input[type="radio"]').forEach((input) => {
        input.addEventListener('change', () => {
            root.querySelectorAll('.barama-location-choice').forEach((label) => label.classList.remove('is-active'));
            input.closest('.barama-location-choice')?.classList.add('is-active');
        });
    });
})();
