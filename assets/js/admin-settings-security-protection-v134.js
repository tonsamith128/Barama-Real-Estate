/* BARAMA_ADMIN_SECURITY_PROTECTION_V134 */
(() => {
    'use strict';
    const params = new URLSearchParams(window.location.search);
    const modal = params.get('security_modal');
    if (modal && ['spam','hack','right-click'].includes(modal)) {
        const target = document.getElementById(`barama-security-${modal}`);
        if (target && typeof target.showPopover === 'function') {
            window.setTimeout(() => {
                try { target.showPopover(); } catch (error) { /* already open */ }
            }, 80);
        }
    }

    let changed = false;
    for (const key of ['saved','message','error','security_modal']) {
        if (params.has(key)) {
            params.delete(key);
            changed = true;
        }
    }
    if (changed) {
        const query = params.toString();
        const clean = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
        window.history.replaceState({}, '', clean);
    }
})();
