/* BARAMA_AUTH_POLICY_LINKS_V117A37
 * Correct registration consent links to the built-in Policy Center.
 * Preserve form fields, agreement checkbox, validation and submit behavior.
 */
(function () {
  'use strict';
  const browserPath = location.pathname.replace(/\/+$/, '') || '/';
  const fallback = /(?:^|\/)index\.php$/i.test(browserPath)
    ? new URLSearchParams(location.search).get('_route') : null;
  const route = fallback && /^\/register\/?$/.test(fallback)
    ? fallback.replace(/\/+$/, '')
    : /\/register$/.test(browserPath) ? '/register' : browserPath;
  if (route !== '/register') return;

  const script = document.currentScript;
  const destinations = Object.freeze({
    '/terms': script && script.getAttribute('data-terms-url') || '/policies?=terms-of-service',
    '/privacy': script && script.getAttribute('data-privacy-url') || '/policies?=privacy-policy'
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
