/* BARAMA_FRONTEND_BACK_TO_TOP_V102 */
(function () {
    'use strict';

    function getScrollTop() {
        return Math.max(
            Number(window.pageYOffset || 0),
            Number(window.scrollY || 0),
            Number(document.documentElement && document.documentElement.scrollTop || 0),
            Number(document.body && document.body.scrollTop || 0)
        );
    }

    function getRevealThreshold() {
        var documentHeight = Math.max(
            document.documentElement ? document.documentElement.scrollHeight : 0,
            document.body ? document.body.scrollHeight : 0
        );
        var viewportHeight = Math.max(
            window.innerHeight || 0,
            document.documentElement ? document.documentElement.clientHeight : 0
        );
        var maxScroll = Math.max(0, documentHeight - viewportHeight);

        if (maxScroll <= 0) {
            return Number.POSITIVE_INFINITY;
        }

        return Math.min(180, Math.max(72, maxScroll * 0.12));
    }

    function init() {
        var button = document.querySelector('[data-back-to-top]');
        if (!button) {
            return;
        }

        var reducedMotion = window.matchMedia
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : null;
        var scheduled = false;

        function update() {
            scheduled = false;
            var visible = getScrollTop() > getRevealThreshold();

            button.setAttribute('data-visible', visible ? 'true' : 'false');
            button.setAttribute('aria-hidden', visible ? 'false' : 'true');
            button.tabIndex = visible ? 0 : -1;
        }

        function scheduleUpdate() {
            if (scheduled) {
                return;
            }

            scheduled = true;

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(update);
            } else {
                window.setTimeout(update, 16);
            }
        }

        button.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                left: 0,
                behavior: reducedMotion && reducedMotion.matches ? 'auto' : 'smooth'
            });
        });

        window.addEventListener('scroll', scheduleUpdate, { passive: true });
        window.addEventListener('resize', scheduleUpdate, { passive: true });

        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
