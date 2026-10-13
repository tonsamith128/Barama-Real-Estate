/* BARAMA_VERIFY_EMAIL_V117A38
 * Verified email UI-only enhancement: original verification actions and forms stay intact.
 * No DOM replacement of inputs, buttons, form, CSRF or send/resend behavior.
 */
(function () {
  'use strict';
  if ((location.pathname.replace(/\/+$/, '') || '/') !== '/verify-email') return;
  const source = document.currentScript;
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
