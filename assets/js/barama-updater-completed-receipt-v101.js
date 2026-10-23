/* BARAMA_COMPLETED_UPDATE_RECEIPT_V101 — presentation only.
 * Server verifies the private completed receipt. No update requests or storage writes. */
(() => {
  'use strict';
  const root = document.getElementById('barama-unified-auto-updater');
  const proof = root?.querySelector('[data-bau-verified-completion]');
  if (!root || !proof) return;
  const version = proof.getAttribute('data-bau-verified-completion') || '';
  if (!/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/.test(version)) return;

  // Older screens use a 7-step discovery list; newer screens already
  // show a 4-stage completed summary. Never downgrade the newer summary.
  const list = root.querySelector('.bau-mainpanel .bau-steps');
  const isNextDiscovery = Boolean(list?.textContent?.includes('Signed feed'));
  if (isNextDiscovery) {
    const heading = root.querySelector('.bau-mainpanel .bau-panel-heading h2');
    if (heading) heading.textContent = 'Next update discovery';
    const step = root.querySelector('.bau-mainpanel .bau-panel-heading .bau-pill');
    if (step) step.textContent = 'Ready for next release';
    const ring = root.querySelector('[data-bau-ring-title]');
    if (ring) ring.textContent = 'Up to date · v' + version;
    const description = root.querySelector('[data-bau-ring-desc]');
    if (description) description.textContent = 'Previous signed file-only update completed';
    const note = root.querySelector('[data-bau-live-message]');
    if (note) note.textContent = 'The previous v' + version + ' installation is complete. This panel checks for future releases.';
  }

  // A download-only flash from the earlier POST can outlive final completion.
  const stale = 'Signed ZIP downloaded to private storage; nothing installed yet.';
  for (const notice of root.querySelectorAll('.bau-feedback-stack [role="status"], .bau-feedback-stack .bau-flash')) {
    if (notice.textContent.trim() === stale) {
      notice.textContent = 'Previous file-only update to v' + version + ' completed and its receipt was verified.';
    }
  }
})();
