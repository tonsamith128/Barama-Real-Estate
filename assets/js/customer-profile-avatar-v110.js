/* Barama Customer Profile Avatar v1.0.10: UI disclosure only; uploads use v107. */
(() => {
  'use strict';
  document.querySelectorAll('[data-avatar-v110]').forEach((root) => {
    const toggle = root.querySelector('[data-avatar-v110-toggle]');
    if (!toggle) return;
    const setOpen = (open) => {
      root.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(!root.classList.contains('is-open')));
    document.addEventListener('pointerdown', (event) => {
      if (!root.contains(event.target)) setOpen(false);
    });
    root.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setOpen(false);
        toggle.focus();
      }
    });
  });
})();
