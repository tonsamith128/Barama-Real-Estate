/* BARAMA_ADMIN_ROLES_PERMISSIONS_V135 */
(() => {
  'use strict';
  const root = document.querySelector('[data-rbac-admin]');
  if (!root) return;
  const closeAll = () => {
    root.querySelectorAll('[data-rbac-modal]').forEach((modal) => { modal.hidden = true; });
    document.body.classList.remove('rbac-modal-open');
  };
  root.querySelectorAll('[data-rbac-open]').forEach((button) => {
    button.addEventListener('click', () => {
      closeAll();
      const slug = button.getAttribute('data-rbac-open');
      const modal = root.querySelector(`[data-rbac-modal="${CSS.escape(slug || '')}"]`);
      if (!modal) return;
      modal.hidden = false;
      document.body.classList.add('rbac-modal-open');
      const focusTarget = modal.querySelector('input:not([disabled]), button:not([disabled])');
      if (focusTarget) focusTarget.focus();
    });
  });
  root.querySelectorAll('[data-rbac-close]').forEach((button) => button.addEventListener('click', closeAll));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeAll(); });
  if (window.history && window.history.replaceState) {
    const url = new URL(window.location.href);
    ['saved', 'error', 'role'].forEach((key) => url.searchParams.delete(key));
    window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
  }
})();
