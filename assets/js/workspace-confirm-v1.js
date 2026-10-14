/* No inline event handlers: delegate confirmations for workspace actions. */
(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    const target = event.target;
    const button = target && typeof target.closest === 'function'
      ? target.closest('[data-barama-confirm]') : null;
    if (!button) return;
    const message = button.getAttribute('data-barama-confirm');
    if (message && !window.confirm(message)) event.preventDefault();
  });
})();
