/* Barama Auto Updater v1.2.1: presentation only. No licensing network calls or storage. */
(() => {
  'use strict';
  const scope = document.querySelector('.bsys-auto-premium');
  const table = scope?.querySelector('[data-premium-table]');
  const host = scope?.querySelector('[data-publisher-control]');
  if (!scope || !table || !host) return;
  const body = table.tBodies[0];
  const triggers = Array.from(table.querySelectorAll('[data-detail-target]'));
  const itemRows = Array.from(body.querySelectorAll('tr.bsys-premium-item'));
  const license = body.querySelector('tr[data-premium-item="license"]');
  const state = host.querySelector('[data-publisher-state]');
  const progress = host.querySelector('[data-publisher-progress]');
  const lifecycle = host.querySelector('[data-publisher-lifecycle]');
  const tag = license?.querySelector('[data-premium-license-tag]');
  const summary = license?.querySelector('[data-premium-license-summary]');
  if (!body || !license || !state || !tag || !summary) return;

  const detailFor = (button) => {
    const id = button.getAttribute('data-detail-target') || '';
    const row = id ? document.getElementById(id) : null;
    return row && body.contains(row) ? row : null;
  };
  const close = (button) => {
    const row = detailFor(button);
    if (row) row.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  };
  triggers.forEach(button => button.addEventListener('click', () => {
    const row = detailFor(button);
    if (!row) return;
    const opening = button.getAttribute('aria-expanded') !== 'true';
    triggers.forEach(close);
    row.hidden = !opening;
    button.setAttribute('aria-expanded', opening ? 'true' : 'false');
  }));
  table.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const active = triggers.find(button => button.getAttribute('aria-expanded') === 'true');
    if (active) { close(active); active.focus(); event.preventDefault(); }
  });

  const reorder = () => {
    // Each item and its associated Detail row must remain adjacent; keep stable tie order.
    const sorted = itemRows.map((item, index) => ({item, index, priority: Number(item.dataset.priority || 0)}))
      .sort((a, b) => (b.priority - a.priority) || (a.index - b.index));
    // Avoid unnecessary DOM moves (and losing focused controls) if already in order.
    const current = Array.from(body.querySelectorAll('tr.bsys-premium-item'));
    if (sorted.every((entry, index) => entry.item === current[index])) return;
    const fragment = document.createDocumentFragment();
    sorted.forEach(({item}) => {
      const id = item.querySelector('[data-detail-target]')?.getAttribute('data-detail-target');
      const detail = id ? document.getElementById(id) : null;
      fragment.append(item);
      if (detail && body.contains(detail)) fragment.append(detail);
    });
    body.append(fragment);
  };
  const sync = () => {
    const name = (state.textContent || '').trim();
    const verified = name === 'Active license verified' && host.dataset.verified === '1';
    const waiting = name === 'Wait before retrying' || name === 'Publisher temporarily unavailable';
    const text = tag.querySelector('[data-publisher-summary]');
    if (text) text.textContent = verified ? 'Verified' : name || 'Not checked';
    tag.classList.remove('bsys-premium-tag--good', 'bsys-premium-tag--needs', 'bsys-premium-tag--neutral');
    tag.classList.add(verified ? 'bsys-premium-tag--good' : waiting ? 'bsys-premium-tag--neutral' : 'bsys-premium-tag--needs');
    // The publisher response is the authority: do not cache or invent a verified session.
    const message = verified ? 'Publisher confirmed the current installation'
      : name === 'Not checked' ? 'Publisher validation not confirmed'
      : waiting ? 'Verification needs retry or connection review'
      : 'Publisher verification needs attention';
    if (summary.firstChild?.nodeType === Node.TEXT_NODE) summary.firstChild.textContent = message;
    const remote = summary.querySelector('[data-publisher-readiness-state]');
    if (remote) remote.textContent = name || 'Not checked';
    const priority = verified ? '20' : '200';
    if (license.dataset.priority !== priority) license.dataset.priority = priority;
    if (host.dataset.checking !== '1') {
      // The old browser verifier sets lifecycle stage=complete but leaves it visible.
      // Hide it only after its request has completed; preserve the live progress UI.
      if (lifecycle) lifecycle.hidden = true;
      if (progress) progress.hidden = true;
      reorder();
    }
  };
  sync();
  if (window.MutationObserver) {
    const observer = new MutationObserver(sync);
    observer.observe(state, {childList:true, characterData:true, subtree:true});
    observer.observe(host, {attributes:true, attributeFilter:['data-verified', 'data-checking']});
    window.addEventListener('pagehide', () => observer.disconnect(), {once:true});
  }
})();
