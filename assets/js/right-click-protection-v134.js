/* BARAMA_RIGHT_CLICK_PROTECTION_V134 */
(() => {
    'use strict';
    const root = document.documentElement;
    if (root.dataset.rightClickProtection !== '1') return;

    const editable = (target) => target instanceof Element
        && !!target.closest('input, textarea, select, [contenteditable="true"], pre, code');

    if (root.dataset.rightClickContextMenu === '1') {
        document.addEventListener('contextmenu', (event) => {
            if (!editable(event.target)) event.preventDefault();
        });
    }

    if (root.dataset.rightClickImageDrag === '1') {
        document.addEventListener('dragstart', (event) => {
            if (event.target instanceof HTMLImageElement) event.preventDefault();
        });
    }
})();
