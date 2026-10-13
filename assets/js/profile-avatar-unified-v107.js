/* Barama Unified Profile Avatar v1.0.7 — one upload control on the current avatar. */
(() => {
  'use strict';
  const types = new Set(['image/jpeg', 'image/png', 'image/webp']);
  const limit = 5 * 1024 * 1024;
  document.querySelectorAll('[data-avatar-v107-upload]').forEach((form) => {
    const input = form.querySelector('[data-avatar-v107-input]');
    const label = form.querySelector('label[for]');
    if (!input) return;
    if (label) label.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault(); input.click();
      }
    });
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) return;
      if (!types.has(file.type) || !file.size || file.size > limit) {
        window.alert('Choose a JPG, PNG, or WebP image up to 5 MB.');
        input.value = ''; return;
      }
      form.closest('[data-avatar-v107]')?.classList.add('is-uploading');
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
    });
  });
  document.querySelectorAll('[data-avatar-v107-remove]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm('Remove your profile image? The default avatar will be restored.')) {
        event.preventDefault();
      }
    });
  });
})();
