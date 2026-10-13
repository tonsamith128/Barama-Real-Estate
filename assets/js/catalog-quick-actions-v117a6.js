/* Barama Catalog v1.0.17-A8 — single-row header actions and on-demand REAL forms.
   No new endpoints; no location or Mapbox data is modified here. */
(() => {
  'use strict';

  const buttons = Array.from(document.querySelectorAll('[data-barama-catalog-open]'));
  if (buttons.length !== 2) return;

  const entries = buttons.map(button => ({
    button,
    panel: document.getElementById(button.getAttribute('aria-controls') || '')
  }));

  if (entries.some(({ panel }) => !panel || panel.tagName !== 'DETAILS')) return;
  const editors = entries[0].panel.closest('.workspace-form-pair');
  if (!editors || entries.some(({ panel }) => panel.parentElement !== editors)) return;

  function update() {
    let expanded = false;
    entries.forEach(({ button, panel }) => {
      const isOpen = panel.open;
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      expanded = expanded || isOpen;
    });
    editors.classList.toggle('barama-catalog-v117a8-expanded', expanded);
  }

  entries.forEach(({ button, panel }) => {
    panel.addEventListener('toggle', update);
    button.addEventListener('click', () => {
      const openNext = !panel.open;
      if (openNext) {
        // Keep only one form visible; preserve field values when switching.
        entries.forEach(entry => { if (entry.panel !== panel) entry.panel.open = false; });
      }
      panel.open = openNext;
      update();
      if (!openNext) return;
      panel.scrollIntoView({
        block:'start',
        behavior: window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
      });
      const field = panel.querySelector('input[name="name"]');
      if (field) field.focus({preventScroll:true});
    });
  });

  editors.classList.add('barama-catalog-v117a8-enhanced');
  update();
})();
