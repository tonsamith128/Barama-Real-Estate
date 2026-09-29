/* BARAMA_WORKSPACE_INDEPENDENT_SIDEBAR_SCROLL_V115 */
(() => {
    'use strict';

    const init = () => {
        const sidebar = document.querySelector('[data-workspace-sidebar]');
        const nav = sidebar?.querySelector('.workspace-nav');

        if (!(sidebar instanceof HTMLElement) || !(nav instanceof HTMLElement)) {
            return;
        }

        const active = nav.querySelector('a.is-active');
        const pathname = window.location.pathname || '/';
        const workspaceRoot = pathname.startsWith('/dashboard')
            ? 'dashboard'
            : pathname.startsWith('/developer')
                ? 'developer'
                : pathname.startsWith('/account')
                    ? 'account'
                    : 'workspace';
        const storageKey = 'barama-workspace-nav-scroll:' + workspaceRoot;

        const clampScroll = (value) => Math.max(
            0,
            Math.min(
                Number.isFinite(value) ? value : 0,
                Math.max(0, nav.scrollHeight - nav.clientHeight)
            )
        );

        const saveScroll = () => {
            try {
                window.sessionStorage.setItem(storageKey, String(Math.round(nav.scrollTop)));
            } catch (_) {
                // Storage is optional; scrolling still works without it.
            }
        };

        const restoreScroll = () => {
            try {
                const stored = Number.parseFloat(
                    window.sessionStorage.getItem(storageKey) || ''
                );

                if (Number.isFinite(stored)) {
                    nav.scrollTop = clampScroll(stored);
                }
            } catch (_) {
                // Ignore unavailable storage.
            }
        };

        const ensureActiveVisible = () => {
            if (!(active instanceof HTMLElement)) {
                return;
            }

            const navRect = nav.getBoundingClientRect();
            const activeRect = active.getBoundingClientRect();
            const breathingRoom = 14;

            const above = activeRect.top < navRect.top + breathingRoom;
            const below = activeRect.bottom > navRect.bottom - breathingRoom;

            if (!above && !below) {
                return;
            }

            const target =
                active.offsetTop
                - ((nav.clientHeight - active.offsetHeight) / 2);

            nav.scrollTop = clampScroll(target);
            saveScroll();
        };

        let saveFrame = 0;

        nav.addEventListener('scroll', () => {
            if (saveFrame) {
                window.cancelAnimationFrame(saveFrame);
            }

            saveFrame = window.requestAnimationFrame(() => {
                saveFrame = 0;
                saveScroll();
            });
        }, { passive: true });

        nav.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', saveScroll, { passive: true });
        });

        restoreScroll();

        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(ensureActiveVisible);
        });

        window.addEventListener('resize', () => {
            window.requestAnimationFrame(ensureActiveVisible);
        }, { passive: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
