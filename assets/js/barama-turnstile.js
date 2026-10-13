/* Barama first-party Turnstile loader: only enabled forms get a challenge. */
(() => {
  'use strict';
  const cfg = document.currentScript;
  if (!cfg) return;
  const key = cfg.dataset.sitekey || '';
  const forms = new Set((cfg.dataset.forms || '').split(',').filter(Boolean));
  const appearance = ['always','execute','interaction-only'].includes(cfg.dataset.appearance)
    ? cfg.dataset.appearance : 'always';
  if (!key || forms.size === 0) return;
  const map = new Map([['/login','login'],['/register','register'],
    ['/forgot-password','forgot_password'],['/contact/messages','contact'],['/inquiries','inquiry']]);
  const render = () => {
    if (!window.turnstile || typeof window.turnstile.render !== 'function') return;
    document.querySelectorAll('form[action]').forEach(form => {
      let path;
      try { path = new URL(form.getAttribute('action'), location.href).pathname; } catch (_) { return; }
      const action = map.get(path);
      if (!forms.has(action) || form.dataset.baramaTurnstileAttached === '1') return;
      form.dataset.baramaTurnstileAttached = '1';
      const holder = document.createElement('div');
      holder.className = 'barama-turnstile-widget';
      holder.setAttribute('aria-label','Bot verification');
      holder.style.margin = '14px 0';
      const submit = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submit) form.insertBefore(holder,submit); else form.appendChild(holder);
      window.turnstile.render(holder,{sitekey:key,action:action,appearance:appearance,'response-field':true});
    });
  };
  window.baramaTurnstileReady = () => {
    render();
    const observer = new MutationObserver(() => render());
    observer.observe(document.body, {childList:true, subtree:true});
  };
  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=baramaTurnstileReady';
  script.async = true;
  script.defer = true;
  document.head.appendChild(script);
})();
