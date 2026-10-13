/* A21 consent hint. No interception of valid submissions, form values, or AJAX hooks. */
(function () {
  'use strict';
  function init() {
    var main = document.querySelector('.barama-a21-contact');
    if (!main) return;
    var forms = main.querySelectorAll('form[data-inquiry-form][data-contact-form]');
    forms.forEach(function (form) {
      var consent = form.querySelector('input[type="checkbox"][name="consent"][required]');
      var error = form.querySelector('#barama-a21-consent-error');
      if (!consent || !error) return; // Consent may be disabled by Admin.
      var showError = function () {
        consent.setAttribute('aria-invalid', 'true');
        error.hidden = false;
      };
      var clearError = function () {
        if (consent.checked) {
          consent.removeAttribute('aria-invalid');
          error.hidden = true;
        }
      };
      consent.addEventListener('invalid', function (event) {
        // Suppress native tooltip for this one checkbox only. All native
        // required-field constraints and existing submit handlers remain active.
        event.preventDefault();
        showError();
      });
      consent.addEventListener('change', clearError);
      consent.addEventListener('input', clearError);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once:true });
  } else {
    init();
  }
})();
