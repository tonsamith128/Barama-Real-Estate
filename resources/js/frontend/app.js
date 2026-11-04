// ===== FRONTEND / 01 / ACCESSIBLE MOBILE NAVIGATION =====
function enhanceMobileNavigation() {
    const menu = document.querySelector('details.site-mobile');
    if (!menu) return;

    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });
    document.addEventListener('pointerdown', (event) => {
        if (menu.open && event.target instanceof Node && !menu.contains(event.target)) {
            menu.open = false;
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceMobileNavigation, { once: true });
} else {
    enhanceMobileNavigation();
}
