/* BARAMA_FRONTEND_BACK_TO_TOP_V100 */
(() => {
    'use strict';

    const init = () => {
        const root = document.documentElement;

        if (!root.classList.contains('barama-public-root')) {
            return;
        }

        if (document.querySelector('[data-back-to-top]')) {
            return;
        }

        const style = document.createElement('style');
        style.setAttribute('data-back-to-top-style', 'v1.0.0');
        style.textContent = `
            .barama-back-to-top {
                position: fixed;
                right: max(18px, env(safe-area-inset-right));
                bottom: max(18px, calc(env(safe-area-inset-bottom) + 18px));
                z-index: 1090;
                display: grid;
                width: 48px;
                height: 48px;
                place-items: center;
                margin: 0;
                padding: 0;
                border: 1px solid color-mix(in srgb, var(--barama-primary, #2563eb) 42%, transparent);
                border-radius: 15px;
                background: color-mix(in srgb, var(--barama-primary, #2563eb) 92%, #ffffff 8%);
                color: #ffffff;
                box-shadow:
                    0 14px 34px color-mix(in srgb, #0f172a 22%, transparent),
                    0 0 0 1px color-mix(in srgb, #ffffff 12%, transparent) inset;
                cursor: pointer;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translateY(12px) scale(.94);
                transition:
                    opacity .2s ease,
                    visibility .2s ease,
                    transform .2s ease,
                    background-color .2s ease,
                    border-color .2s ease,
                    box-shadow .2s ease;
                -webkit-tap-highlight-color: transparent;
            }

            .barama-back-to-top[data-visible="true"] {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: translateY(0) scale(1);
            }

            .barama-back-to-top:hover {
                background: color-mix(in srgb, var(--barama-primary, #2563eb) 82%, #0f172a 18%);
                border-color: color-mix(in srgb, var(--barama-primary, #2563eb) 68%, #ffffff 32%);
                box-shadow:
                    0 16px 38px color-mix(in srgb, #0f172a 28%, transparent),
                    0 0 0 1px color-mix(in srgb, #ffffff 16%, transparent) inset;
                transform: translateY(-2px) scale(1.02);
            }

            .barama-back-to-top:focus-visible {
                outline: 3px solid color-mix(in srgb, var(--barama-primary, #2563eb) 30%, transparent);
                outline-offset: 3px;
            }

            .barama-back-to-top svg {
                width: 21px;
                height: 21px;
                fill: none;
                stroke: currentColor;
                stroke-width: 2;
                stroke-linecap: round;
                stroke-linejoin: round;
                pointer-events: none;
            }

            html[data-bs-theme="dark"] .barama-back-to-top {
                border-color: color-mix(in srgb, var(--barama-primary, #4f7cff) 58%, #dbeafe 12%);
                background: color-mix(in srgb, var(--barama-primary, #4f7cff) 80%, #0f172a 20%);
                box-shadow:
                    0 16px 38px rgba(0, 0, 0, .34),
                    0 0 0 1px rgba(255, 255, 255, .07) inset;
            }

            @media (max-width: 575.98px) {
                .barama-back-to-top {
                    right: max(14px, env(safe-area-inset-right));
                    bottom: max(14px, calc(env(safe-area-inset-bottom) + 14px));
                    width: 44px;
                    height: 44px;
                    border-radius: 14px;
                }

                .barama-back-to-top svg {
                    width: 19px;
                    height: 19px;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .barama-back-to-top {
                    transition: none;
                }
            }
        `;
        document.head.appendChild(style);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'barama-back-to-top';
        button.setAttribute('data-back-to-top', '');
        button.setAttribute('data-visible', 'false');
        button.setAttribute('aria-label', 'Back to top');
        button.setAttribute('title', 'Back to top');
        button.setAttribute('aria-hidden', 'true');
        button.tabIndex = -1;
        button.innerHTML = [
            '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">',
            '<path d="M12 19V5"/>',
            '<path d="m6 11 6-6 6 6"/>',
            '</svg>'
        ].join('');
        document.body.appendChild(button);

        const reducedMotion = window.matchMedia
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : null;
        const threshold = 420;
        let scheduled = false;

        const update = () => {
            scheduled = false;
            const visible = window.scrollY > threshold;

            button.setAttribute('data-visible', visible ? 'true' : 'false');
            button.setAttribute('aria-hidden', visible ? 'false' : 'true');
            button.tabIndex = visible ? 0 : -1;
        };

        const scheduleUpdate = () => {
            if (scheduled) {
                return;
            }

            scheduled = true;
            window.requestAnimationFrame(update);
        };

        button.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                left: 0,
                behavior: reducedMotion?.matches ? 'auto' : 'smooth'
            });
        });

        window.addEventListener('scroll', scheduleUpdate, { passive: true });
        window.addEventListener('resize', scheduleUpdate, { passive: true });
        update();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
