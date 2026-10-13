(() => {
    const root = document.querySelector('[data-email-template-manager][data-email-template-version="137"]');
    if (!root) return;

    const search = root.querySelector('[data-email-template-search]');
    const group = root.querySelector('[data-email-template-group-filter]');
    const rows = Array.from(root.querySelectorAll('[data-email-template-row]'));
    const empty = root.querySelector('[data-email-template-filter-empty]');
    const count = root.querySelector('[data-email-template-visible-count]');

    const apply = () => {
        const needle = (search?.value || '').trim().toLowerCase();
        const selectedGroup = (group?.value || '').trim();
        let visible = 0;

        rows.forEach((row) => {
            const haystack = (row.getAttribute('data-search') || '').toLowerCase();
            const rowGroup = row.getAttribute('data-group') || '';
            const matches = (!needle || haystack.includes(needle))
                && (!selectedGroup || rowGroup === selectedGroup);
            row.hidden = !matches;
            if (matches) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
        if (count) count.textContent = String(visible);
    };

    search?.addEventListener('input', apply);
    group?.addEventListener('change', apply);
    apply();
})();
