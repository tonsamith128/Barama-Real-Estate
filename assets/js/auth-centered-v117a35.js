/* BARAMA_AUTH_CENTERED_V117A35
 * Post-A34 accessible presentation enhancement. No changes to inputs' name,
 * POST values, event handlers, CSRF, backend services, OAuth or password flows.
 */
(function () {
  'use strict';
  // Apply the same premium UI to clean and legacy PHP-routed auth paths.
  // Do not trust _route on unrelated paths or change form submissions.
  const browserPath = location.pathname.replace(/\/+$/, '') || '/';
  const fallback = /(?:^|\/)index\.php$/i.test(browserPath)
    ? new URLSearchParams(location.search).get('_route') : null;
  const cleanMatch = browserPath.match(/\/(?:login|register|forgot-password)$/);
  const route = fallback && /^\/(?:login|register|forgot-password)\/?$/.test(fallback)
    ? fallback.replace(/\/+$/, '')
    : cleanMatch ? cleanMatch[0] : browserPath;
  if (!['/login', '/register', '/forgot-password'].includes(route)) return;
  const source = document.currentScript;
  const light = (source && source.getAttribute('data-site-logo-light') || '').trim();
  const dark = (source && source.getAttribute('data-site-logo-dark') || '').trim() || light;
  const siteName = (source && source.getAttribute('data-site-name') || '').trim() || 'Website';
  const NS = 'http://www.w3.org/2000/svg';

  function svgIcon() {
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    svg.classList.add('barama-auth-a35__name-icon');
    const circle = document.createElementNS(NS, 'circle');
    circle.setAttribute('cx', '12'); circle.setAttribute('cy', '8'); circle.setAttribute('r', '3.25');
    const path = document.createElementNS(NS, 'path');
    path.setAttribute('d','M5.25 19c0-3.75 2.75-6 6.75-6s6.75 2.25 6.75 6');
    svg.append(circle, path);
    return svg;
  }
  function currentIsDark() {
    const h = document.documentElement;
    const b = document.body;
    const explicit = (h.getAttribute('data-bs-theme') || h.getAttribute('data-theme') ||
      (b && (b.getAttribute('data-bs-theme') || b.getAttribute('data-theme'))) || '').toLowerCase();
    if (explicit === 'dark') return true;
    if (explicit === 'light') return false;
    return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  function configureBrand(card) {
    if (!light) {
      // No valid configured logo URL: retain existing logo rather than inventing one.
      console.warn('[Barama A35] Configured site logo URL missing; existing brand retained.');
      return;
    }
    let brand = card.querySelector('.barama-auth-a33__brand, .barama-auth-a35__brand');
    let logo = brand && brand.querySelector('img');
    if (!logo) {
      // The existing site-owned image is preferable to a second copied logo.
      const existing = Array.from(document.querySelectorAll('img[data-barama-logo-light]'))
        .find(image => !image.closest('nav,header,footer') && !card.contains(image));
      if (!brand) {
        brand = document.createElement('div');
        brand.className = 'barama-auth-a35__brand';
        card.insertBefore(brand, card.firstChild);
      }
      if (existing) {
        const host = existing.closest('a') || existing;
        brand.appendChild(host);
        logo = existing;
      } else {
        const home = document.createElement('a');
        home.href = '/';
        logo = document.createElement('img');
        home.appendChild(logo); brand.appendChild(home);
      }
    }
    // Use the same Appearance / Website Identity source as the regular site header.
    logo.setAttribute('data-barama-logo-light',light);
    logo.setAttribute('data-barama-logo-dark',dark);
    logo.alt = siteName + ' logo';
    const apply = () => {
      const uri = currentIsDark() ? dark : light;
      if (logo.getAttribute('src') !== uri) logo.setAttribute('src',uri);
    };
    apply();
    const observer = new MutationObserver(apply);
    observer.observe(document.documentElement, {
      attributes:true,attributeFilter:['data-bs-theme','data-theme']
    });
    observer.observe(document.body, {
      attributes:true,attributeFilter:['data-bs-theme','data-theme']
    });
    if (typeof window.matchMedia === 'function') {
      const mq = window.matchMedia('(prefers-color-scheme: dark)');
      if (typeof mq.addEventListener === 'function') mq.addEventListener('change',apply);
    }
  }
  function names(card) {
    if (route !== '/register') return;
    const grid = card.querySelector('.barama-auth-a33__name-grid');
    if (!grid) return;
    const labels = Array.from(grid.children).filter(x => x.tagName === 'LABEL' && x.querySelector('input'));
    if (labels.length !== 2) return;
    const names = ['First name', 'Last name'];
    labels.forEach((label, index) => {
      if (label.dataset.baramaA35Ready === '1') return;
      const input = label.querySelector('input');
      if (!input || input.type !== 'text') return;
      // A33 dynamically generated <label>text<input></label>. Remove only the
      // visible text node, never replace the input or its listeners.
      Array.from(label.childNodes).filter(node => node.nodeType === 3).forEach(node => node.remove());
      label.classList.add('barama-auth-a35__name-field');
      input.setAttribute('aria-label',names[index]);
      input.placeholder = names[index];
      input.setAttribute('autocomplete',index === 0 ? 'given-name' : 'family-name');
      input.insertAdjacentElement('beforebegin',svgIcon());
      label.dataset.baramaA35Ready = '1';
    });
  }
  function apply() {
    const body = document.body;
    if (!body || body.dataset.baramaA35Ready === '1') return;
    if (body.dataset.baramaA33Ready !== '1' || body.dataset.baramaA34Ready !== '1') {
      console.warn('[Barama A35] A33/A34 not initialized, leaving existing view intact.');
      return;
    }
    const card = body.querySelector('.barama-auth-a33__card');
    const heading = card && card.querySelector('.barama-auth-a34__heading');
    if (!card || !heading) return;
    configureBrand(card);
    names(card);
    body.classList.add('barama-auth-a35');
    body.dataset.baramaA35Ready = '1';
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',apply,{once:true});
  else apply();
})();
