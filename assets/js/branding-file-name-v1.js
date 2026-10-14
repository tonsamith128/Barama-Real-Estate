/* Progressive enhancement for website-identity file selection; no inline handlers. */
(function () {
  'use strict';
  document.addEventListener('change', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-barama-branding-file]')) return;
    const card = input.closest('.barama-branding-card-v104');
    const label = card && card.querySelector('.barama-branding-file-name-v104');
    if (label) label.textContent = input.files && input.files.length ? input.files[0].name : 'Current image retained';
  });
})();
