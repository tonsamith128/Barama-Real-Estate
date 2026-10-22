/* BARAMA_UPDATER_VISIBLE_V103: toast dismiss only, no installation or permission changes. */
(() => {
  'use strict';
  const root = document.getElementById('barama-unified-auto-updater');
  if (!root) return;
  const groups = root.querySelectorAll('.bau-feedback-stack');
  groups.forEach(group => {
    group.querySelectorAll('.bau-flash').forEach(notice => {
      if (notice.querySelector('.bau-feedback-close')) return;
      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'bau-feedback-close';
      close.setAttribute('aria-label', 'Dismiss updater notification');
      close.textContent = '×';
      close.addEventListener('click', () => {
        notice.remove();
        if (!group.querySelector('.bau-flash')) group.remove();
      });
      notice.appendChild(close);
    });
  });
})();
