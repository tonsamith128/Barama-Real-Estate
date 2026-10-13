/* BARAMA_WORKSPACE_RAIL_TOOLTIP_V117A15
 * Admin, Account, Developer: icon label on hover / keyboard focus in desktop rail.
 * Tooltip is portaled to body to avoid sidebar overflow clipping.
 * No route changes, no user content HTML, no change to mobile drawer/toggle state.
 */
(() => {
  'use strict';
  const sidebar = document.querySelector('[data-workspace-sidebar]');
  const nav = sidebar && sidebar.querySelector('.workspace-nav');
  const root = document.documentElement;
  if (!sidebar || !nav) return;
  const desktop = window.matchMedia('(min-width: 992px)');
  let active = null;
  let tooltip = null;
  let mode = '';
  const collapsed = () => desktop.matches && root.classList.contains('workspace-sidebar-is-collapsed');
  const getLink = (target) => {
    if (!(target instanceof Element)) return null;
    const link = target.closest('a[href]');
    return link && nav.contains(link) ? link : null;
  };
  function ensureTooltip() {
    if (tooltip) return tooltip;
    tooltip = document.createElement('div');
    tooltip.id = 'barama-workspace-rail-tooltip-v117a15';
    tooltip.className = 'barama-workspace-rail-tooltip-v117a15';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.appendChild(tooltip);
    return tooltip;
  }
  function restoreTitle(link) {
    if (link && link.hasAttribute('data-barama-rail-saved-title')) {
      const saved = link.getAttribute('data-barama-rail-saved-title');
      if (saved) link.setAttribute('title', saved);
      link.removeAttribute('data-barama-rail-saved-title');
    }
  }
  function hide() {
    if (active) {
      active.removeAttribute('aria-describedby');
      restoreTitle(active);
    }
    active = null;
    mode = '';
    if (tooltip) tooltip.hidden = true;
  }
  function position() {
    if (!active || !tooltip || tooltip.hidden || !collapsed()) { hide(); return; }
    const sidebarRect = sidebar.getBoundingClientRect();
    const rect = active.getBoundingClientRect();
    if (rect.bottom <= sidebarRect.top || rect.top >= window.innerHeight || rect.bottom <= 0) { hide(); return; }
    const ttRect = tooltip.getBoundingClientRect();
    const width = ttRect.width;
    const height = ttRect.height;
    let left = sidebarRect.right + 10;
    if (left + width > window.innerWidth - 10) left = Math.max(10, sidebarRect.left - width - 10);
    const top = Math.max(10, Math.min(window.innerHeight - height - 10, rect.top + (rect.height - height) / 2));
    tooltip.style.left = Math.round(left) + 'px';
    tooltip.style.top = Math.round(top) + 'px';
  }
  function show(link, inputMode) {
    if (!collapsed()) { hide(); return; }
    const label = (link.getAttribute('aria-label') || link.getAttribute('title') || '').trim();
    if (!label) return;
    if (active !== link) hide();
    active = link;
    mode = inputMode;
    if (link.hasAttribute('title') && !link.hasAttribute('data-barama-rail-saved-title')) {
      link.setAttribute('data-barama-rail-saved-title', link.getAttribute('title'));
      link.removeAttribute('title'); // suppress competing native browser tooltip
    }
    const tt = ensureTooltip();
    tt.textContent = label;
    tt.hidden = false;
    link.setAttribute('aria-describedby', tt.id);
    position();
  }
  nav.addEventListener('pointerover', (e) => {
    if (e.pointerType === 'touch') return;
    const link = getLink(e.target);
    if (!link || (active === link && mode === 'pointer')) return;
    show(link, 'pointer');
  });
  nav.addEventListener('pointerout', (e) => {
    if (mode !== 'pointer' || !active) return;
    const previous = getLink(e.target);
    const next = getLink(e.relatedTarget);
    if (previous === active && next !== active) hide();
  });
  nav.addEventListener('focusin', (e) => {
    const link = getLink(e.target);
    if (link) show(link, 'keyboard');
  });
  nav.addEventListener('focusout', (e) => {
    const next = getLink(e.relatedTarget);
    if (active && next !== active) hide();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });
  window.addEventListener('scroll', hide, {passive:true});
  nav.addEventListener('scroll', hide, {passive:true});
  window.addEventListener('resize', hide, {passive:true});
  const toggle = document.querySelector('[data-workspace-sidebar-toggle]');
  if (toggle) toggle.addEventListener('click', hide);
  new MutationObserver(() => { if (!collapsed()) hide(); }).observe(root, {attributes: true, attributeFilter: ['class']});
})();
