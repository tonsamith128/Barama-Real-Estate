/* Barama unified update lifecycle: in-flight indicator only; no simulated percentage or installation. */
(()=>{'use strict';const root=document.getElementById('barama-unified-auto-updater');if(!root)return;const check=root.querySelector('[data-bau-check-form]');if(check){check.addEventListener('submit',()=>{root.dataset.bauBusy='1';const b=check.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Checking signed publisher…'}const title=root.querySelector('[data-bau-ring-title]');const desc=root.querySelector('[data-bau-ring-desc]');const message=root.querySelector('[data-bau-live-message]');if(title)title.textContent='Checking for updates';if(desc)desc.textContent='Contacting the trusted publisher and validating its response';if(message)message.textContent='The next confirmed result will appear when the publisher responds. No update is being installed.';});}const install=root.querySelector('[data-bau-install-form]');if(install && !install.hasAttribute('data-bau-portable-flow')){install.addEventListener('submit',e=>{if(!window.confirm('Install the publisher-signed update now? The updater will verify authorization and backup affected files before changes. Proceed only if you have a verified recovery plan.')){e.preventDefault();return;}root.dataset.bauBusy='1';const btn=install.querySelector('button[type=submit]');if(btn){btn.disabled=true;btn.textContent='Submitting protected installation…';}})}})();
/* BARAMA_AUTO_UPDATER_EXPERIENCE_V102 — progressive enhancement only.
 * Never changes worker readiness, entitlement, job status, form action, or install button state.
 */
(() => {
  'use strict';
  const root = document.getElementById('barama-unified-auto-updater');
  if (!root || root.dataset.bauExperienceV102 === '1') return;
  root.dataset.bauExperienceV102 = '1';

  // Put the operational workflow first; move existing nodes without recreating
  // forms or changing the server-rendered authorization or state.
  const columns = root.querySelector('.bau-columns');
  const versions = root.querySelector('.bau-versions');
  const readiness = root.querySelector('.bau-readiness');
  const overview = root.querySelector('.bau-bottom');
  if (columns && versions && readiness && overview && columns.parentElement === root) {
    root.insertBefore(versions, overview);
    root.insertBefore(readiness, overview);
  }

  // The existing link remains a standard navigation link; only styling changes.
  if (readiness) {
    const link = readiness.querySelector('a[href]');
    if (link && /\/dashboard\/license(?:[/?#]|$)/.test(link.getAttribute('href') || '')) {
      link.classList.add('bau-button', 'bau-license-action');
      link.textContent = 'Manage license';
    }
  }

  // Turn previous full-width notices into non-blocking, dismissible messages.
  const flashes = Array.from(root.querySelectorAll('.bau-flash'));
  if (flashes.length) {
    const stack = document.createElement('div');
    stack.className = 'bau-feedback-stack';
    stack.setAttribute('aria-label', 'Updater notifications');
    root.appendChild(stack);
    flashes.forEach(flash => {
      const message = (flash.textContent || '').trim().toLowerCase();
      if (/not |unavailable|failed|denied|error|blocked|expired/.test(message)) {
        flash.classList.add('bau-feedback-warning');
      } else if (/no package was downloaded|no newer|not downloaded/.test(message)) {
        flash.classList.add('bau-feedback-info');
      }
      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'bau-feedback-close';
      close.setAttribute('aria-label', 'Dismiss updater notification');
      close.textContent = '×';
      close.addEventListener('click', () => {
        flash.remove();
        if (!stack.children.length) stack.remove();
      });
      flash.appendChild(close);
      stack.appendChild(flash);
    });
  }
})();
