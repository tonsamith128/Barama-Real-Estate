/* Barama v1.0.12 — scoped notification tray, no network requests. */
(() => {
  'use strict';
  const trays = Array.from(document.querySelectorAll('[data-notification-tray]'));
  if (!trays.length) return;
  function closeTray(tray) {
    const button = tray.querySelector('[data-notification-toggle]');
    const panel = tray.querySelector('[data-notification-panel]');
    if (!button || !panel) return;
    panel.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  }
  trays.forEach((tray) => {
    const button = tray.querySelector('[data-notification-toggle]');
    const panel = tray.querySelector('[data-notification-panel]');
    if (!button || !panel) return;
    button.addEventListener('click', () => {
      const shouldOpen = panel.hidden;
      trays.forEach(closeTray);
      panel.hidden = !shouldOpen;
      button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    });
  });
  document.addEventListener('pointerdown', (event) => {
    if (!event.target.closest('[data-notification-tray]')) trays.forEach(closeTray);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      trays.forEach(closeTray);
      document.activeElement?.blur?.();
    }
  });
})();
