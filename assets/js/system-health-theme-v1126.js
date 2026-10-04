/* Barama Public System Status — pre-paint theme v1.1.26 */
(() => {
    'use strict';

    const root = document.documentElement;
    let theme = 'light';

    try {
        const stored = window.localStorage.getItem('barama-theme');

        if (stored === 'light' || stored === 'dark') {
            theme = stored;
        } else if (
            window.matchMedia
            && window.matchMedia('(prefers-color-scheme: dark)').matches
        ) {
            theme = 'dark';
        }
    } catch (error) {
        if (
            window.matchMedia
            && window.matchMedia('(prefers-color-scheme: dark)').matches
        ) {
            theme = 'dark';
        }
    }

    root.setAttribute('data-bs-theme', theme);
})();
