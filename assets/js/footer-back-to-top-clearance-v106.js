/* Barama v1.0.6 — Footer-aware floating Back-to-Top button.
   Only adjusts a CSS variable on the existing button. Does not change
   social links, footer content, theme controls or scroll behavior. */
(function () {
    'use strict';

    function init() {
        var button = document.querySelector('[data-back-to-top]');
        var footer = document.querySelector('.site-footer__bottom');
        if (!button || !footer || !document.body.classList.contains('barama-public-surface')) return;

        var property = '--barama-footer-clearance';
        var scheduled = false;
        function overlaps(a, b, gap) {
            return a.left < b.right + gap && a.right > b.left - gap &&
                a.top < b.bottom + gap && a.bottom > b.top - gap;
        }
        function update() {
            scheduled = false;
            // Measure the normal button position on every scroll/resize;
            // measuring a previously displaced button causes oscillation.
            button.style.removeProperty(property);
            if (button.getAttribute('data-visible') !== 'true') return;
            var buttonRect = button.getBoundingClientRect();
            var links = footer.querySelectorAll('.site-footer__social-link, .site-footer__theme');
            var earliest = Infinity;
            var collision = false;
            for (var i = 0; i < links.length; i++) {
                var el = links[i];
                var rect = el.getBoundingClientRect();
                if (!rect.width || !rect.height || rect.bottom < 0 || rect.top > window.innerHeight) continue;
                if (overlaps(buttonRect, rect, 12)) collision = true;
                if (rect.top < earliest) earliest = rect.top;
            }
            if (!collision) return;
            // Clear the entire footer-bottom strip, including copyright,
            // rather than moving just a few pixels above one icon.
            var row = footer.getBoundingClientRect();
            var boundary = row.top > buttonRect.height + 25
                ? Math.min(row.top, earliest)
                : earliest;
            var neededBottom = Math.ceil(window.innerHeight - boundary + 16);
            if (window.innerHeight - neededBottom - buttonRect.height < 12) {
                // Extremely short viewports: keep the button clear of icons
                // by moving it above their row even if partially offscreen.
                neededBottom = Math.ceil(window.innerHeight - earliest + 12);
            }
            button.style.setProperty(property, neededBottom + 'px');
        }
        function schedule() {
            if (scheduled) return;
            scheduled = true;
            window.requestAnimationFrame(update);
        }
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
        if ('ResizeObserver' in window) {
            new ResizeObserver(schedule).observe(footer);
        }
        schedule();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
