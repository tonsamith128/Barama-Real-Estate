/* BARAMA_AUTH_PREMIUM_V117A33
   Progressive enhancement only. Preserve original forms, URLs, CSRF, password helpers,
   social OAuth links and backend name field. Avoid adding unsupported providers. */
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
        host.style.display = 'none';
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
