/* BARAMA_AUTH_COPY_V117A34
 * Progressive presentation-only refinement of A33. Exact English copy matching;
 * no changes to forms, CSRF, registrations, reset requests, OAuth, or passwords.
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
  const pathname = queryRoute && /^\/(?:login|register|forgot-password)\/?$/.test(queryRoute)
    ? queryRoute.replace(/\/+$/, '')
    : cleanMatch ? cleanMatch[0] : browserPath;
  const routes = ['/login', '/register', '/forgot-password'];
  if (!routes.includes(pathname)) return;

  const script = document.currentScript;
  const configuredName = (script && script.getAttribute('data-barama-website-name') || '').trim();
  const websiteName = configuredName || 'Barama Real Estate';

  const normalize = text => (text || '').replace(/\s+/g, ' ').trim();
  function exactText(root, selector, pattern) {
    return Array.from(root.querySelectorAll(selector)).find(el =>
      !el.querySelector('input,textarea,button,select,form,a') && pattern.test(normalize(el.textContent)) &&
      !Array.from(el.children).some(child => pattern.test(normalize(child.textContent)))
    );
  }
  function iconOnly(el) {
    if (!el || normalize(el.textContent) !== '') return false;
    if (el.matches('a,button,input,label,form,nav') || el.querySelector('a,button,input,label,form,nav,img')) return false;
    if (el.querySelector('svg,i') || el.matches('svg,i')) return true;
    return /(?:^|[\s_-])(?:auth-)?(?:icon|badge|symbol|glyph)(?:$|[\s_-])/.test(String(el.className || '').toLowerCase());
  }
  function removeHeaderIcon(heading, card) {
    // Only inspect the heading's immediate header ancestors. Never touch form icons.
    let current = heading;
    for (let depth = 0; depth < 3; depth++) {
      const parent = current.parentElement;
      if (!parent || parent === card || parent.contains(card)) break;
      for (const sibling of Array.from(parent.children)) {
        if (sibling !== current && iconOnly(sibling)) {
          sibling.remove();
          return true;
        }
      }
      current = parent;
    }
    return false;
  }
  function run() {
    const body = document.body;
    if (!body || body.dataset.baramaA34Ready === '1') return;
    if (body.dataset.baramaA33Ready !== '1') {
      console.warn('[Barama A34] A33 auth layout unavailable; preserving original page.');
      return;
    }
    const card = body.querySelector('.barama-auth-a33__card');
    if (!card) return;

    const headingPattern = pathname === '/login' ? /^(sign in|welcome back)$/i :
      pathname === '/register' ? /^(create (an? )?account|register)$/i :
      /^(forgot password|reset password)$/i;
    const heading = exactText(card, '.barama-auth-a33__heading,h1,h2,h3,h4,.auth-title,.card-title', headingPattern);
    if (!heading) {
      console.warn('[Barama A34] Auth heading not recognized; nothing changed.');
      return;
    }
    // Locate the exact original text; leave localizations or modified copy alone.
    const subtitles = {
      '/login': /^Continue to your Barama workspace\.?$/i,
      '/register': /^Set up your secure customer profile\.?$/i,
      '/forgot-password': /^Enter the email used for your account\.?$/i
    };
    const subtitle = exactText(card,
      '.barama-auth-a33__subtitle,p,small,span,.auth-subtitle,.auth-description,.card-subtitle,div',
      subtitles[pathname]);

    if (pathname === '/login') {
      if (normalize(heading.textContent).toLowerCase() === 'sign in') heading.textContent = 'Welcome Back';
      if (subtitle) subtitle.remove();
    } else if (pathname === '/register') {
      if (subtitle) subtitle.remove();
    } else if (subtitle) {
      subtitle.textContent = 'Enter your account email. If the account exists, ' +
        websiteName + ' will send a secure one-time reset link that expires automatically.';
      subtitle.classList.add('barama-auth-a34__reset-description');
    }
    removeHeaderIcon(heading, card);
    heading.classList.add('barama-auth-a34__heading');
    card.classList.add('barama-auth-a34__card');
    body.classList.add('barama-auth-a34', 'barama-auth-a34--' + pathname.slice(1));
    body.dataset.baramaA34Ready = '1';
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, {once:true});
  else run();
})();
