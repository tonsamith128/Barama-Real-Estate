/* BARAMA_AUTH_TITLE_CENTERING_V117A36
 * Force a dedicated centered title/helper region directly below the configured site logo.
 * Presentation-only: no changes to auth functions, routes, CSRF, OAuth, or form payloads.
 */
(function () {
  'use strict';
  // Resolve the public route in both clean and installer-selected query mode.
  // This changes presentation only; forms and their server-side paths are unchanged.
  const browserPath = location.pathname.replace(/\/+$/, '') || '/';
  const queryRoute = /(?:^|\/)index\.php$/i.test(browserPath)
    ? new URLSearchParams(location.search).get('_route')
    : null;
  const cleanMatch = browserPath.match(/\/(?:login|register|forgot-password)$/);
  const route = queryRoute && /^\/(?:login|register|forgot-password)\/?$/.test(queryRoute)
    ? queryRoute.replace(/\/+$/, '')
    : cleanMatch ? cleanMatch[0] : browserPath;
  if (!['/login', '/register', '/forgot-password'].includes(route)) return;

  function findHelper(card, heading) {
    const selectors = [
      '.barama-auth-a34__reset-description',
      '.barama-auth-a33__subtitle',
      '.barama-auth-a36__helper'
    ];
    for (const selector of selectors) {
      const el = card.querySelector(selector);
      if (el && el !== heading) return el;
    }
    return null;
  }

  function apply() {
    const body = document.body;
    if (!body || body.dataset.baramaA36Ready === '1') return;
    if (body.dataset.baramaA35Ready !== '1') {
      console.warn('[Barama A36] A35 not initialized; existing auth view preserved.');
      return;
    }
    const card = body.querySelector('.barama-auth-a33__card');
    if (!card) return;
    const brand = card.querySelector('.barama-auth-a35__brand, .barama-auth-a33__brand');
    const heading = card.querySelector('.barama-auth-a34__heading, .barama-auth-a33__heading, h1, h2, h3, h4, .auth-title, .card-title');
    if (!brand || !heading) return;

    let hero = card.querySelector('.barama-auth-a36__hero');
    if (!hero) {
      hero = document.createElement('div');
      hero.className = 'barama-auth-a36__hero';
      brand.insertAdjacentElement('afterend', hero);
    }

    const helper = findHelper(card, heading);
    hero.appendChild(heading);
    heading.classList.add('barama-auth-a36__heading');
    heading.style.textAlign = 'center';
    heading.style.marginInline = 'auto';

    if (helper) {
      hero.appendChild(helper);
      helper.classList.add('barama-auth-a36__helper');
      helper.style.textAlign = 'center';
      helper.style.marginInline = 'auto';
    }

    hero.style.textAlign = 'center';
    hero.style.alignItems = 'center';
    body.classList.add('barama-auth-a36');
    body.dataset.baramaA36Ready = '1';
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply, { once: true });
  else apply();
})();
