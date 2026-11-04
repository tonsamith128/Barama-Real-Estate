// ===== ADMIN / 01 / ACCESSIBLE WORKSPACE NAVIGATION =====
function enhanceNavigation() {
    const navigation = document.querySelector('.workspace-nav');
    const search = navigation?.querySelector('[data-workspace-search]');
    const input = search?.querySelector('[data-workspace-nav-search]');
    const status = search?.querySelector('[data-workspace-search-status]');

    if (navigation && search && input && status) {
        const links = Array.from(navigation.querySelectorAll('a'));
        const headings = Array.from(navigation.querySelectorAll('.workspace-nav__label'));

        const filter = () => {
            const term = input.value.trim().toLocaleLowerCase();
            let total = 0;
            for (const link of links) {
                const match = term === '' || link.textContent.toLocaleLowerCase().includes(term);
                link.hidden = !match;
                if (match) total++;
            }
            for (const heading of headings) {
                let item = heading.nextElementSibling;
                let visible = false;
                while (item && !item.classList.contains('workspace-nav__label')) {
                    if (item.matches('a') && !item.hidden) visible = true;
                    item = item.nextElementSibling;
                }
                heading.hidden = term !== '' && !visible;
            }
            status.textContent = term === '' ? '' : String(total) + ' matching ' + (total === 1 ? 'page' : 'pages');
        };

        search.hidden = false;
        input.addEventListener('input', filter);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                input.value = '';
                filter();
                input.blur();
            }
        });
        document.addEventListener('keydown', (event) => {
            const target = event.target;
            const typing = target instanceof HTMLElement &&
                (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
            if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                input.focus();
            }
        });
    }

    const mobile = document.querySelector('details.workspace-mobile-menu');
    mobile?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.open) {
            mobile.open = false;
            mobile.querySelector('summary')?.focus();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceNavigation, { once: true });
} else {
    enhanceNavigation();
}
