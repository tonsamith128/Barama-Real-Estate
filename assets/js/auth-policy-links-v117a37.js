/* BARAMA_AUTH_POLICY_LINKS_V117A37
 * Correct registration consent links to the built-in Policy Center.
 * Preserve form fields, agreement checkbox, validation and submit behavior.
 */
(function () {
  'use strict';
  if ((location.pathname.replace(/\/+$/, '') || '/') !== '/register') return;

  const destinations = Object.freeze({
    '/terms': '/policies?=terms-of-service',
    '/privacy': '/policies?=privacy-policy'
  });

  function correctLinks() {
    const card = document.querySelector('.barama-auth-a33__card');
    if (!card) return;
    const form = card.querySelector('form');
    if (!form) return;
    const agreement = form.querySelector('.barama-auth-a33__terms');
    if (!agreement) return;
    for (const anchor of agreement.querySelectorAll('a[href]')) {
      // Change only the A33-generated local, known-broken links.
      const original = anchor.getAttribute('href');
      if (Object.prototype.hasOwnProperty.call(destinations, original)) {
        anchor.setAttribute('href', destinations[original]);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', correctLinks, { once: true });
  } else {
    correctLinks();
  }
})();
