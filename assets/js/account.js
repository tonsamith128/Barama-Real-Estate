/* BARAMA REAL ESTATE — ACCOUNT JS — stable area bundle. */
(()=>{
"use strict";
const modules=Object.create(null);

/* ===== profile-avatar-unified ===== */
modules["profile-avatar-unified"]=function(__baramaScript){
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

};

/* ===== customer-profile-avatar ===== */
modules["customer-profile-avatar"]=function(__baramaScript){
/* Barama Customer Profile Avatar v1.0.10: UI disclosure only; uploads use v107. */
(() => {
  'use strict';
  document.querySelectorAll('[data-avatar-v110]').forEach((root) => {
    const toggle = root.querySelector('[data-avatar-v110-toggle]');
    if (!toggle) return;
    const setOpen = (open) => {
      root.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(!root.classList.contains('is-open')));
    document.addEventListener('pointerdown', (event) => {
      if (!root.contains(event.target)) setOpen(false);
    });
    root.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setOpen(false);
        toggle.focus();
      }
    });
  });
})();

};

/* ===== auth-premium ===== */
modules["auth-premium"]=function(__baramaScript){
/* BARAMA_AUTH_PREMIUM_V117A33
   Progressive enhancement only. Preserve original forms, URLs, CSRF, password helpers,
   social OAuth links and backend name field. Avoid adding unsupported providers. */
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
  function run() {
    const body = document.body;
    if (!body || body.dataset.baramaA33Ready === '1') return;
    const form = Array.from(document.forms).find(f => f.querySelector('input[type="password"], input[type="email"], input[name="email"]'));
    if (!form) { console.warn('[Barama A33] Auth form not detected; original UI left intact.'); return; }
    const possibilities = [
      '.auth-card', '.auth-panel', '.auth-box', '.auth-form-card', '.auth-shell__card',
      '.auth-standalone-card', '.auth-container__card', '.authentication-card',
      '.auth-wrapper .card', '.login-card', '.register-card', '.card'
    ];
    let card = null;
    for (const selector of possibilities) {
      const match = form.closest(selector);
      if (match) { card = match; break; }
    }
    if (!card) {
      let el = form.parentElement;
      while (el && el !== body) {
        if (el.querySelector('form') === form && el.querySelectorAll('form').length === 1 &&
            (el.className && /auth|panel|card|shell/i.test(String(el.className)))) {card = el;break;}
        el = el.parentElement;
      }
    }
    if (!card) { console.warn('[Barama A33] Auth card not detected; original UI left intact.'); return; }
    body.dataset.baramaA33Ready = '1';
    body.classList.add('barama-auth-a33', 'barama-auth-a33--' + route.slice(1));
    card.classList.add('barama-auth-a33__card');

    // Relocate the actual brand image/link without changing its target or theme-aware attributes.
    const images = Array.from(document.querySelectorAll('img'));
    const logo = images.find(img => /barama/i.test((img.alt || '') + ' ' + (img.getAttribute('src') || '')) && !img.closest('nav'));
    if (logo && !card.contains(logo)) {
      const host = logo.closest('a') || logo;
      const header = document.createElement('div');
      header.className = 'barama-auth-a33__brand';
      header.appendChild(host);
      card.insertBefore(header, card.firstChild);
    } else if (logo && card.contains(logo)) {
      const host = logo.closest('a') || logo;
      const parent = host.parentElement;
      if (parent) parent.classList.add('barama-auth-a33__brand');
    }

    const headings = Array.from(card.querySelectorAll('h1,h2,h3,h4,strong,.auth-title,.card-title'));
    const wanted = route === '/register' ? /create (an? )?account|register/i : route === '/login' ? /sign in|welcome back|login/i : /forgot password|reset password/i;
    const heading = headings.find(el => wanted.test((el.textContent || '').trim())) || headings.find(el => /^H[1-4]$/.test(el.tagName));
    if (heading) heading.classList.add('barama-auth-a33__heading');
    if (heading) {
      const next = heading.nextElementSibling;
      if (next && /^(P|SMALL|SPAN|DIV)$/.test(next.tagName) && !next.querySelector('input,button')) {
        next.classList.add('barama-auth-a33__subtitle');
      }
    }
    const submit = form.querySelector('button[type="submit"],input[type="submit"]');
    if (submit) submit.classList.add('barama-auth-a33__submit');

    // Preserve and reuse only configured/visible OAuth links; do not invent logins.
    const socials = Array.from(card.querySelectorAll('a[href],button[data-provider]')).filter(el => {
      const haystack = ((el.getAttribute('href') || '') + ' ' + (el.dataset.provider || '') + ' ' + (el.textContent || '')).toLowerCase();
      return /google|facebook|linkedin|apple|github|microsoft/.test(haystack) &&
        (/google|facebook|linkedin|apple|github|microsoft/.test(el.getAttribute('href') || '') || !!el.dataset.provider) &&
        !el.closest('.barama-auth-a33__social-grid');
    });
    if (socials.length) {
      const row = document.createElement('div');row.className = 'barama-auth-a33__social-grid';
      const reference = socials[0].parentElement;
      if (reference) reference.parentElement.insertBefore(row, reference.nextSibling);
      else card.appendChild(row);
      socials.forEach(el => row.appendChild(el));
    }

    if (route !== '/register') return;
    // Split the *visual* name entry while keeping the original backend POST key and its value.
    const existing = form.querySelector('input[name="full_name"],input[name="fullname"],input[name="name"],input[id="full_name"],input[id="fullname"],input[id="name"],input[autocomplete="name"],input[placeholder*="Full name" i]');
    if (existing && !existing.dataset.baramaA33Split && existing.type !== 'hidden') {
      const oldValue = existing.value.trim().split(/\s+/).filter(Boolean);
      const grid = document.createElement('div');grid.className = 'barama-auth-a33__name-grid';
      function field(text, auto, val) {
        const label = document.createElement('label');label.textContent = text;
        const inp = document.createElement('input');inp.type = 'text';inp.required = true;
        inp.autocomplete = auto;inp.placeholder = text;inp.maxLength = 120;
        inp.value = val; label.appendChild(inp);grid.appendChild(label);return inp;
      }
      const first = field('First name', 'given-name', oldValue[0] || '');
      const last = field('Last name', 'family-name', oldValue.slice(1).join(' '));
      const sync = function() {
        existing.value = (first.value.trim() + ' ' + last.value.trim()).trim();
        existing.dispatchEvent(new Event('change', {bubbles:true}));
      };
      first.addEventListener('input',sync);last.addEventListener('input',sync);
      const host = existing.closest('.form-group,.mb-3,.field,.input-group,.form-field,.auth-field') || existing.parentElement;
      if (host && host !== form) {
        host.parentNode.insertBefore(grid,host);
        host.classList.add('barama-auth-a33__original-name-field');
        existing.dataset.baramaA33Split = '1';
        // preserve original field name, CSRF, constraints, and submission payload shape.
        existing.type = 'hidden';existing.required = false;
        sync();
      }
      form.addEventListener('submit',function(evt) {
        sync();
        if (!first.value.trim() || !last.value.trim()) {
          evt.preventDefault();(first.value.trim() ? last : first).focus();
        }
      },true);
    } else if (!existing) console.warn('[Barama A33] Name control unknown; preserved original field.');

    // One native checkbox for UI agreement. Server-side policy remains unchanged.
    const termsExisting = Array.from(form.querySelectorAll('input[type="checkbox"]')).find(input => {
      const label = input.closest('label');const txt = (label?.textContent || '') + ' ' + (input.name || '');
      return /terms|privacy|consent|agree|accept/i.test(txt);
    });
    if (termsExisting) {termsExisting.required = true;return;}
    if (!submit) return;
    const row = document.createElement('label');row.className = 'barama-auth-a33__terms';
    const box = document.createElement('input');box.type = 'checkbox';box.required = true;box.name = 'a33_ui_terms_agreement';box.value = '1';
    const copy = document.createElement('span');
    copy.append('I agree to the ');
    const terms = document.createElement('a');terms.href = '/terms';terms.textContent = 'Terms and Conditions';
    const policy = document.createElement('a');policy.href = '/privacy';policy.textContent = 'Privacy Policy';
    copy.append(terms, ' and ', policy, '.');row.append(box,copy);
    submit.parentElement.insertBefore(row,submit);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',run,{once:true}); else run();
})();

};

/* ===== auth-copy ===== */
modules["auth-copy"]=function(__baramaScript){
/* BARAMA_AUTH_COPY_V117A34
 * Progressive presentation-only refinement of A33. Exact English copy matching;
 * no changes to forms, CSRF, registrations, reset requests, OAuth, or passwords.
 */
(function () {
  'use strict';
  // Apply the same premium UI to clean and legacy PHP-routed auth paths.
  // Do not trust _route on unrelated paths or change form submissions.
  const browserPath = location.pathname.replace(/\/+$/, '') || '/';
  const fallback = /(?:^|\/)index\.php$/i.test(browserPath)
    ? new URLSearchParams(location.search).get('_route') : null;
  const cleanMatch = browserPath.match(/\/(?:login|register|forgot-password)$/);
  const pathname = fallback && /^\/(?:login|register|forgot-password)\/?$/.test(fallback)
    ? fallback.replace(/\/+$/, '')
    : cleanMatch ? cleanMatch[0] : browserPath;
  const routes = ['/login', '/register', '/forgot-password'];
  if (!routes.includes(pathname)) return;

  const script = __baramaScript;
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

};

/* ===== auth-centered ===== */
modules["auth-centered"]=function(__baramaScript){
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
  const source = __baramaScript;
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

};

/* ===== auth-title-centering ===== */
modules["auth-title-centering"]=function(__baramaScript){
/* BARAMA_AUTH_TITLE_CENTERING_V117A36
 * Force a dedicated centered title/helper region directly below the configured site logo.
 * Presentation-only: no changes to auth functions, routes, CSRF, OAuth, or form payloads.
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

    if (helper) {
      hero.appendChild(helper);
      helper.classList.add('barama-auth-a36__helper');
    }
    body.classList.add('barama-auth-a36');
    body.dataset.baramaA36Ready = '1';
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply, { once: true });
  else apply();
})();

};

/* ===== auth-policy-links ===== */
modules["auth-policy-links"]=function(__baramaScript){
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

  const script = __baramaScript;
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

};

/* ===== verify-email-auth ===== */
modules["verify-email-auth"]=function(__baramaScript){
/* BARAMA_VERIFY_EMAIL_V117A38
 * Verified email UI-only enhancement: original verification actions and forms stay intact.
 * No DOM replacement of inputs, buttons, form, CSRF or send/resend behavior.
 */
(function () {
  'use strict';
  if ((location.pathname.replace(/\/+$/, '') || '/') !== '/verify-email') return;
  const source = __baramaScript;
  const light = (source?.getAttribute('data-site-logo-light') || '').trim();
  const dark = (source?.getAttribute('data-site-logo-dark') || '').trim() || light;
  const siteName = (source?.getAttribute('data-site-name') || '').trim() || 'Website';

  function findText(root, pattern) {
    return [...root.querySelectorAll('p,span,small,div,strong,label')]
      .find(el => el.children.length === 0 && pattern.test((el.textContent || '').trim()));
  }
  function isDark() {
    for (const el of [document.documentElement, document.body]) {
      if (!el) continue;
      const setting = (el.getAttribute('data-bs-theme') || el.getAttribute('data-theme') || '').toLowerCase();
      if (setting === 'dark') return true;
      if (setting === 'light') return false;
    }
    return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  function findCard(heading) {
    const selectors = ['.auth-premium__card','.auth-premium__grid','.verify-email-card','.verification-card','.auth-card','.auth-panel','.card'];
    for (const selector of selectors) {
      const candidate = heading.closest(selector);
      if (candidate && candidate.querySelector('form')) return candidate;
    }
    let node = heading.parentElement;
    while (node && node !== document.body) {
      if (node.querySelector('form') && node.querySelectorAll('form').length <= 3) {
        // Prefer the smallest form-containing node with a recognizable card/panel identity.
        if (/card|panel|auth|verify|security|container/i.test(String(node.className)) ||
            node.tagName === 'MAIN') return node;
      }
      node = node.parentElement;
    }
    return null;
  }
  function hideHeaderIcon(card, eyebrow, heading) {
    const nodes = [...card.querySelectorAll('div,span,i')];
    const options = nodes.filter(el => {
      if (el === eyebrow || el.contains(eyebrow) || el.contains(heading)) return false;
      if (el.querySelector('input,button,form,a')) return false;
      if (!el.querySelector('svg')) return false;
      if ((el.textContent || '').trim()) return false;
      if (!/icon|symbol|badge/i.test(String(el.className))) return false;
      return Boolean(el.compareDocumentPosition(heading) & Node.DOCUMENT_POSITION_FOLLOWING);
    });
    // Select only a decorative icon appearing before the eyebrow/header, never form controls.
    const adjacent = [eyebrow?.previousElementSibling, eyebrow?.parentElement?.previousElementSibling]
      .find(el => el && el.querySelector('svg') && !el.querySelector('input,button,form,a') &&
                  !(el.textContent || '').trim());
    const last = adjacent || options[options.length - 1];
    if (last) { last.classList.add('barama-verify-a38__removed-icon'); last.setAttribute('aria-hidden','true'); }
  }
  function brand(card, eyebrow, heading) {
    if (!light) return;
    if (card.querySelector('.barama-verify-a38__brand')) return;
    // Reuse only an already rendered site logo from within this verification card.
    let logo = [...card.querySelectorAll('img')]
      .find(img => /logo/i.test((img.alt || '') + ' ' + (img.className || '')));
    const holder = document.createElement('div');
    holder.className = 'barama-verify-a38__brand';
    if (logo) {
      const host = logo.closest('a') || logo;
      holder.appendChild(host);
    } else {
      const link = document.createElement('a');
      link.href = '/';
      link.setAttribute('aria-label',siteName + ' home');
      logo = document.createElement('img');
      logo.alt = siteName + ' logo';
      link.appendChild(logo);
      holder.appendChild(link);
    }
    logo.alt = siteName + ' logo';
    const anchor = eyebrow || heading;
    anchor.parentNode.insertBefore(holder, anchor);
    function applyLogo() {
      const url = isDark() ? dark : light;
      if (logo.getAttribute('src') !== url) logo.setAttribute('src',url);
    }
    logo.addEventListener('error', () => { holder.style.display='none'; }, {once:true});
    applyLogo();
    const observer = new MutationObserver(applyLogo);
    observer.observe(document.documentElement,{attributes:true,attributeFilter:['data-theme','data-bs-theme']});
    if (document.body) observer.observe(document.body,{attributes:true,attributeFilter:['data-theme','data-bs-theme']});
    if (typeof window.matchMedia === 'function') {
      const media = window.matchMedia('(prefers-color-scheme: dark)');
      media.addEventListener?.('change',applyLogo);
    }
  }
  function apply() {
    if (!document.body || document.body.dataset.baramaVerifyA38 === '1') return;
    const heading = [...document.querySelectorAll('h1,h2,h3,h4')]
      .find(el => /^verify your email$/i.test((el.textContent || '').trim()));
    if (!heading) return;  // Authenticated verify page not rendered; leave original site untouched.
    const card = findCard(heading);
    if (!card) return;
    const eyebrow = findText(card,/^account security$/i);
    const helper = [...card.querySelectorAll('p,div,span')].find(el =>
      el.children.length === 0 && /confirm your registered email address/i.test((el.textContent || '').trim()));
    const headerRoot = heading.parentElement;
    // Never turn a form-containing ancestor into a layout wrapper.
    if (headerRoot && !headerRoot.querySelector('form')) headerRoot.classList.add('barama-verify-a38__intro');
    heading.classList.add('barama-verify-a38__title');
    if (eyebrow) eyebrow.classList.add('barama-verify-a38__eyebrow');
    if (helper) helper.classList.add('barama-verify-a38__helper');
    hideHeaderIcon(card,eyebrow,heading);
    brand(card,eyebrow,heading);
    card.classList.add('barama-verify-a38__card');
    document.body.classList.add('barama-verify-a38');
    document.body.dataset.baramaVerifyA38 = '1';
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',apply,{once:true});
  else apply();
})();

};

/* ===== account-settings-full ===== */
modules["account-settings-full"]=function(__baramaScript){
/* BARAMA_ACCOUNT_SETTINGS_FULL_FUNCTIONS_V1130R3 */
(() => {
    'use strict';

    const normalizedPath = location.pathname.replace(/\/+$/, '');
    const allowed = new Set([
        '/account/settings/basic',
        '/account/settings/profile',
        '/account/settings/privacy'
    ]);
    if (!allowed.has(normalizedPath)) return;

    const $ = (selector, root = document) => root.querySelector(selector);

    const iconText = (text) => {
        const span = document.createElement('span');
        span.setAttribute('aria-hidden', 'true');
        span.textContent = text;
        return span;
    };

    const button = (label, className = '') => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = `barama-account-v1130-button ${className}`.trim();
        el.append(iconText('↗'));
        const copy = document.createElement('span');
        copy.textContent = label;
        el.append(copy);
        return el;
    };

    const actionContainer = (form) => {
        const known = form.querySelector('.barama-settings-actions');
        if (known) {
            known.classList.add('barama-account-v1130-actions');
            return known;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'barama-account-v1130-actions';
        form.append(wrapper);
        return wrapper;
    };

    const findFormContaining = (...selectors) =>
        Array.from(document.forms).find((form) => selectors.every((selector) => form.querySelector(selector)));

    const basic = () => {
        const form = findFormContaining(
            'select[name="preferred_language"]',
            'select[name="timezone"]'
        );
        if (!form || form.dataset.accountV1130Basic === 'ready') return;
        form.dataset.accountV1130Basic = 'ready';

        const language = $('select[name="preferred_language"]', form);
        const timezone = $('select[name="timezone"]', form);
        const actions = actionContainer(form);

        if (!actions.querySelector('[data-account-detect-timezone]')) {
            const detect = button('Use browser timezone');
            detect.dataset.accountDetectTimezone = '1';
            actions.append(detect);

            detect.addEventListener('click', () => {
                let browserTimezone = '';
                try {
                    browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
                } catch (_) {}

                if (!browserTimezone) {
                    window.alert('Your browser did not provide a timezone.');
                    return;
                }

                const option = Array.from(timezone.options)
                    .find((item) => item.value === browserTimezone);

                if (!option) {
                    window.alert(`Browser timezone "${browserTimezone}" is not available in this account timezone list.`);
                    return;
                }

                timezone.value = browserTimezone;
                timezone.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        if (!actions.querySelector('[data-account-reset-defaults]')) {
            const reset = button('Reset defaults');
            reset.dataset.accountResetDefaults = '1';
            reset.classList.add('barama-account-v1130-button--danger');
            actions.append(reset);

            reset.addEventListener('click', () => {
                if (!window.confirm('Reset language to English and timezone to UTC?')) return;

                let action = form.querySelector('input[name="basic_action"]');
                if (!action) {
                    action = document.createElement('input');
                    action.type = 'hidden';
                    action.name = 'basic_action';
                    form.append(action);
                }
                action.value = 'reset';

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        }

        const preview = document.createElement('div');
        preview.className = 'barama-account-v1130-preview';
        preview.innerHTML = `
            <span class="barama-account-v1130-preview__eyebrow">Regional preview</span>
            <strong class="barama-account-v1130-preview__date" data-account-preview-date>Loading preview…</strong>
            <div class="barama-account-v1130-preview__meta">
                <span>Selected timezone: <strong data-account-preview-zone></strong></span>
                <span>Browser timezone: <strong data-account-browser-zone></strong></span>
            </div>
        `;
        actions.insertAdjacentElement('afterend', preview);

        const previewDate = $('[data-account-preview-date]', preview);
        const previewZone = $('[data-account-preview-zone]', preview);
        const browserZone = $('[data-account-browser-zone]', preview);

        let browserTimezone = '';
        try {
            browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (_) {}
        browserZone.textContent = browserTimezone || 'Unavailable';

        const localeFor = (value) => ({
            en: 'en-US',
            km: 'km-KH',
            zh: 'zh-CN'
        }[value] || 'en-US');

        const renderPreview = () => {
            const zone = timezone.value || 'UTC';
            previewZone.textContent = zone;
            try {
                previewDate.textContent = new Intl.DateTimeFormat(localeFor(language.value || 'en'), {
                    dateStyle: 'full',
                    timeStyle: 'short',
                    timeZone: zone
                }).format(new Date());
            } catch (_) {
                previewDate.textContent = 'Preview unavailable';
            }
        };

        language.addEventListener('change', renderPreview);
        timezone.addEventListener('change', renderPreview);
        renderPreview();
    };

    const profile = () => {
        const form = findFormContaining(
            'input[name="name"]',
            'input[name="phone"]'
        ) || findFormContaining('input[name="name"]');

        if (!form || form.dataset.accountV1130Profile === 'ready') return;
        form.dataset.accountV1130Profile = 'ready';

        const actions = actionContainer(form);
        if (actions.querySelector('[data-account-discard-profile]')) return;

        const discard = button('Discard changes');
        discard.dataset.accountDiscardProfile = '1';
        discard.replaceChildren(iconText('↺'), Object.assign(document.createElement('span'), { textContent: 'Discard changes' }));
        actions.append(discard);

        discard.addEventListener('click', () => form.reset());
    };

    const privacy = () => {
        const clearHistoryInput = $('input[name="privacy_action"][value="clear_history"]');
        if (!clearHistoryInput) return;

        const clearHistoryForm = clearHistoryInput.closest('form');
        if (!clearHistoryForm || document.querySelector('[data-account-v1130-privacy-tools]')) return;

        const host =
            clearHistoryForm.closest('.barama-settings-card') ||
            clearHistoryForm.parentElement ||
            clearHistoryForm;

        const panel = document.createElement('section');
        panel.dataset.accountV1130PrivacyTools = '1';
        panel.innerHTML = `
            <div class="barama-account-v1130-data-grid">
                <article class="barama-account-v1130-data-card">
                    <span class="barama-account-v1130-data-card__icon" aria-hidden="true">⇄</span>
                    <strong>Comparison list</strong>
                    <p>Remove all properties currently retained for side-by-side comparison.</p>
                    <form method="post" data-account-clear-compare>
                        <button class="barama-account-v1130-button barama-account-v1130-button--danger" type="submit">
                            Clear comparison
                        </button>
                    </form>
                </article>
                <article class="barama-account-v1130-data-card">
                    <span class="barama-account-v1130-data-card__icon" aria-hidden="true">♡</span>
                    <strong>Saved properties</strong>
                    <p>Review and manage the properties you intentionally saved.</p>
                    <a class="barama-account-v1130-card-action" href="/account/saved">Manage saved properties</a>
                </article>
                <article class="barama-account-v1130-data-card">
                    <span class="barama-account-v1130-data-card__icon" aria-hidden="true">◌</span>
                    <strong>Saved searches & alerts</strong>
                    <p>Manage search alerts independently from your recent-view history.</p>
                    <a class="barama-account-v1130-card-action" href="/account/alerts">Manage alerts</a>
                </article>
            </div>
            <div class="barama-account-v1130-note">
                Clearing recent views or comparisons does not delete saved properties, alerts, inquiries, viewing requests, or your account.
            </div>
        `;

        const compareForm = $('[data-account-clear-compare]', panel);
        compareForm.action = clearHistoryForm.action || location.pathname;

        const csrf =
            clearHistoryForm.querySelector('input[name="_token"]') ||
            clearHistoryForm.querySelector('input[name="csrf_token"]') ||
            clearHistoryForm.querySelector('input[type="hidden"]');

        if (csrf) compareForm.append(csrf.cloneNode(true));

        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'privacy_action';
        action.value = 'clear_compare';
        compareForm.append(action);

        compareForm.addEventListener('submit', (event) => {
            if (!window.confirm('Clear your entire property comparison list?')) {
                event.preventDefault();
            }
        });

        host.append(panel);
    };

    if (normalizedPath === '/account/settings/basic') basic();
    if (normalizedPath === '/account/settings/profile') profile();
    if (normalizedPath === '/account/settings/privacy') privacy();
})();

};

/* ===== account-profile ===== */
modules["account-profile"]=function(__baramaScript){
/* BARAMA_ACCOUNT_PROFILE_AVATAR_PREVIEW_V1131R2 */
(() => {
    'use strict';

    if (location.pathname.replace(/\/+$/, '') !== '/account/settings/profile') return;

    const form = document.querySelector('[data-profile-avatar-form]');
    if (!form) return;

    const input = form.querySelector('[data-profile-avatar-input]');
    const dropzone = form.querySelector('[data-profile-avatar-dropzone]');
    const fileName = form.querySelector('[data-profile-avatar-name]');
    const submit = form.querySelector('[data-profile-avatar-submit]');
    const preview = document.querySelector('[data-profile-avatar-preview]');

    if (!input || !dropzone || !fileName || !submit || !preview) return;

    const acceptedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
    const maxBytes = 5 * 1024 * 1024;
    const originalPreviewMarkup = preview.innerHTML;
    let renderToken = 0;

    const restoreOriginalPreview = () => {
        preview.innerHTML = originalPreviewMarkup;
        preview.classList.remove('is-previewing');
    };

    const setReadyState = (file, canSubmit = true) => {
        fileName.textContent = file?.name || 'No file selected';
        submit.disabled = !canSubmit;
    };

    const rejectFile = (message) => {
        input.value = '';
        submit.disabled = true;
        fileName.textContent = 'No file selected';
        restoreOriginalPreview();
        window.alert(message);
    };

    const validateFile = (file) => {
        if (!file) return 'Choose a profile avatar.';
        if (!acceptedTypes.has(file.type)) return 'Choose a JPG, PNG, or WebP image.';
        if (file.size < 1) return 'The selected profile avatar is empty.';
        if (file.size > maxBytes) return 'Profile avatar must be 5 MB or smaller.';
        return '';
    };

    const drawBitmapPreview = async (file, token) => {
        if (typeof createImageBitmap !== 'function') return false;

        let bitmap;
        try {
            bitmap = await createImageBitmap(file);
        } catch (_) {
            return false;
        }

        if (token !== renderToken) {
            if (bitmap && typeof bitmap.close === 'function') bitmap.close();
            return true;
        }

        const pixelSize = 192;
        const canvas = document.createElement('canvas');
        canvas.width = pixelSize;
        canvas.height = pixelSize;
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.display = 'block';
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', 'Selected profile avatar preview');

        const context = canvas.getContext('2d', { alpha: false });
        if (!context) {
            if (bitmap && typeof bitmap.close === 'function') bitmap.close();
            return false;
        }

        const sourceWidth = bitmap.width || 0;
        const sourceHeight = bitmap.height || 0;
        if (sourceWidth < 1 || sourceHeight < 1) {
            if (bitmap && typeof bitmap.close === 'function') bitmap.close();
            return false;
        }

        const crop = Math.min(sourceWidth, sourceHeight);
        const sourceX = Math.max(0, (sourceWidth - crop) / 2);
        const sourceY = Math.max(0, (sourceHeight - crop) / 2);

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, pixelSize, pixelSize);
        context.drawImage(
            bitmap,
            sourceX, sourceY, crop, crop,
            0, 0, pixelSize, pixelSize
        );

        if (bitmap && typeof bitmap.close === 'function') bitmap.close();

        preview.replaceChildren(canvas);
        preview.classList.add('is-previewing');
        return true;
    };

    const drawFileReaderFallback = (file, token) => new Promise((resolve) => {
        if (typeof FileReader !== 'function') {
            resolve(false);
            return;
        }

        const reader = new FileReader();

        reader.onerror = () => resolve(false);
        reader.onload = () => {
            if (token !== renderToken || typeof reader.result !== 'string') {
                resolve(token !== renderToken);
                return;
            }

            const image = new Image();
            image.decoding = 'async';

            image.onload = () => {
                if (token !== renderToken) {
                    resolve(true);
                    return;
                }

                image.alt = '';
                preview.replaceChildren(image);
                preview.classList.add('is-previewing');
                resolve(true);
            };

            image.onerror = () => resolve(false);
            image.src = reader.result;
        };

        reader.readAsDataURL(file);
    });

    const renderPreview = async (file) => {
        const token = ++renderToken;

        preview.classList.add('is-loading');
        preview.setAttribute('aria-busy', 'true');

        let rendered = false;

        try {
            rendered = await drawBitmapPreview(file, token);
            if (!rendered) {
                rendered = await drawFileReaderFallback(file, token);
            }
        } finally {
            if (token === renderToken) {
                preview.classList.remove('is-loading');
                preview.removeAttribute('aria-busy');

                if (!rendered) {
                    restoreOriginalPreview();
                    fileName.textContent = `${file.name} · preview unavailable, ready to upload`;
                }
            }
        }
    };

    const applySelectedFile = (file, inputBacked) => {
        const problem = validateFile(file);
        if (problem) {
            rejectFile(problem);
            return;
        }

        setReadyState(file, inputBacked);
        renderPreview(file);

        if (!inputBacked) {
            fileName.textContent = `${file.name} · click to choose before saving`;
        }
    };

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            ++renderToken;
            restoreOriginalPreview();
            setReadyState(null, false);
            return;
        }

        applySelectedFile(file, true);
    });

    ['dragenter', 'dragover'].forEach((type) => {
        dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragleave', 'drop'].forEach((type) => {
        dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const file = event.dataTransfer?.files?.[0];
        if (!file) return;

        const problem = validateFile(file);
        if (problem) {
            rejectFile(problem);
            return;
        }

        let inputBacked = false;

        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;

            const attached = input.files?.[0];
            inputBacked = Boolean(
                attached
                && attached.name === file.name
                && attached.size === file.size
                && attached.type === file.type
            );
        } catch (_) {
            inputBacked = false;
        }

        applySelectedFile(file, inputBacked);
    });

    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            input.click();
        }
    });

    // Never submit an avatar form unless the native file input really carries a file.
    form.addEventListener('submit', (event) => {
        const file = input.files?.[0];
        const problem = validateFile(file);

        if (problem) {
            event.preventDefault();
            submit.disabled = true;
            window.alert(problem);
        }
    });
})();

};

const seen=new Set();
for(const marker of document.querySelectorAll('script[type="application/x-barama-module"][data-barama-module]')){
  const key=marker.getAttribute('data-barama-module');
  if(seen.has(key)||!Object.prototype.hasOwnProperty.call(modules,key))continue;
  seen.add(key);
  try{modules[key](marker);}catch(error){console.error('[Barama asset module]',key,error);}
}
})();
