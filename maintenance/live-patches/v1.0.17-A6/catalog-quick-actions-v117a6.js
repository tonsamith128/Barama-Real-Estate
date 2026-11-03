/* Barama v1.0.17-A6 — open the existing real Catalog creation forms. */
(() => {
  'use strict';
  const buttons = Array.from(document.querySelectorAll('[data-barama-catalog-open]'));
  if (!buttons.length) return;
  const setState = () => buttons.forEach(button => {
    const target = document.getElementById(button.getAttribute('aria-controls') || '');
    button.setAttribute('aria-expanded', target?.open ? 'true' : 'false');
  });
  buttons.forEach(button => {
    const target = document.getElementById(button.getAttribute('aria-controls') || '');
    if (!target || target.tagName !== 'DETAILS') return;
    target.addEventListener('toggle', setState);
    button.addEventListener('click', () => {
      // Preserve user-entered text in either form: never close another editor.
      target.open = true;
      setState();
      target.scrollIntoView({block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
      const field = target.querySelector('input[name="name"]');
      if (field) field.focus({preventScroll: true});
    });
  });
  setState();
})();
