/* Barama Plans row actions — viewport-safe positioning v1.8.1.
   Native details/summary remains keyboard and no-JS operable. */
(() => {
  'use strict';

  const page = document.querySelector('[data-plans-workspace]');
  if (!page) return;
  const details = Array.from(page.querySelectorAll('.finance-plans__table details.workspace-row-actions'));
  if (!details.length) return;

  const margin = 12;
  const closeAll = (except = null) => {
    details.forEach(entry => {
      if (entry !== except && entry.open) entry.open = false;
    });
  };

  const position = entry => {
    if (!entry.open || !entry.isConnected) return;
    const trigger = entry.querySelector('summary');
    const menu = entry.querySelector('.workspace-row-actions__menu');
    if (!trigger || !menu) return;

    const bounds = page.getBoundingClientRect();
    const rect = trigger.getBoundingClientRect();
    const width = Math.min(menu.offsetWidth || 174, Math.max(0, window.innerWidth - margin * 2));
    const height = menu.offsetHeight || 0;

    // Keep the dropdown inside the actual Plans page, not clipped off the
    // right of the screen or outside the main workspace content.
    const leftBoundary = Math.max(margin, bounds.left + margin);
    const rightBoundary = Math.min(window.innerWidth - margin, bounds.right - margin);
    const maxLeft = Math.max(leftBoundary, rightBoundary - width);
    const left = Math.min(maxLeft, Math.max(leftBoundary, rect.right - width));

    // Prefer beneath the trigger; flip above the row when near the bottom.
    const below = window.innerHeight - rect.bottom - margin;
    const above = rect.top - margin;
    const openAbove = below < height + 6 && above > below;
    const top = openAbove
      ? Math.max(margin, rect.top - height - 6)
      : Math.min(Math.max(margin, rect.bottom + 6), Math.max(margin, window.innerHeight - height - margin));

    menu.style.left = Math.round(left) + 'px';
    menu.style.top = Math.round(top) + 'px';
    menu.style.right = 'auto';
    menu.style.bottom = 'auto';
    menu.style.maxHeight = Math.max(70, window.innerHeight - margin * 2) + 'px';
    menu.style.overflowY = 'auto';
  };

  details.forEach(entry => {
    entry.addEventListener('toggle', () => {
      if (!entry.open) return;
      closeAll(entry);
      window.requestAnimationFrame(() => position(entry));
    });
  });

  document.addEventListener('pointerdown', event => {
    if (!details.some(entry => entry.contains(event.target))) closeAll();
  });

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const active = details.find(entry => entry.open);
    if (!active) return;
    active.open = false;
    active.querySelector('summary')?.focus();
    event.preventDefault();
  });

  // The existing plan editor handles these buttons with event delegation.
  // Close the popover after a successful click without changing its action.
  document.addEventListener('click', event => {
    if (event.target.closest('[data-plan-edit], [data-plan-duplicate]')) closeAll();
  });

  // A floating menu must not drift from its row on any ancestor scroll.
  document.addEventListener('scroll', () => closeAll(), true);
  window.addEventListener('resize', () => closeAll());
})();
