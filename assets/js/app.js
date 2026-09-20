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
})();
