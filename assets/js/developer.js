/* BARAMA REAL ESTATE — DEVELOPER JS — stable area bundle. */
(()=>{
"use strict";
const modules=Object.create(null);

/* ===== developer-application ===== */
modules["developer-application"]=function(__baramaScript){
(() => {
    'use strict';
    document.querySelectorAll('.barama-application-v115__form').forEach((form) => {
        const radios = [...form.querySelectorAll('input[name="application_kind"]')];
        const type = form.querySelector('select[name="company_type"]');
        if (!type || !radios.length) return;
        const selected = () => radios.find((r) => r.checked)?.value || 'developer';
        radios.forEach((radio) => radio.addEventListener('change', () => {
            if (!radio.checked) return;
            if (selected() === 'developer') type.value = 'developer';
            else if (type.value === 'developer') type.value = 'agency';
        }));
        type.addEventListener('change', () => {
            const target = type.value === 'developer' ? 'developer' : 'company';
            const radio = radios.find((r) => r.value === target);
            if (radio) radio.checked = true;
        });
    });
})();

};

/* ===== developer-create-pages ===== */
modules["developer-create-pages"]=function(__baramaScript){
/* BARAMA v1.0.9 - Dedicated creation pages; existing POST endpoints, new navigation. */
(() => {
    'use strict';
    const page = document.querySelector('[data-developer-create-page]');
    const form = page?.querySelector('[data-developer-create-form]');
    if (!page || !form) return;
    const message = page.querySelector('[data-create-feedback]');
    const submit = form.querySelector('[data-create-submit]');
    const company = form.querySelector('select[name="company_id"]');
    const project = form.querySelector('select[name="project_id"]');
    const pricing = form.querySelector('[data-pricing-period]');
    const offer = form.querySelector('[data-transaction-type]');
    let submitting = false;
    function show(messageText, success = false) {
        if (!message) return;
        message.textContent = messageText;
        message.hidden = false;
        message.classList.toggle('is-success', success);
        message.classList.toggle('is-error', !success);
        if (!success) message.focus();
    }
    function filterProjects() {
        if (!company || !project) return;
        let removed = false;
        for (const opt of project.options) {
            if (!opt.value) continue;
            const visible = opt.dataset.companyId === company.value;
            opt.hidden = !visible;
            opt.disabled = !visible;
            if (!visible && opt.selected) removed = true;
        }
        if (removed) project.value = '';
    }
    function updatePricing() {
        if (!offer || !pricing) return;
        const rental = offer.value === 'rent';
        pricing.required = rental;
        if (!rental) pricing.value = '';
    }
    company?.addEventListener('change', filterProjects);
    offer?.addEventListener('change', updatePricing);
    if (company?.options.length === 2) company.selectedIndex = 1;
    filterProjects(); updatePricing();
    form.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        if (submitting || !form.reportValidity()) return;
        submitting = true;
        if (submit) submit.disabled = true;
        if (message) message.hidden = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true) {
                throw new Error(result?.message || 'Unable to save. Please check your entries and try again.');
            }
            const resource = form.dataset.createResource;
            const ids = {company:'company_id',project:'project_id',property:'property_id',listing:'listing_id'};
            const id = String(result?.data?.[ids[resource]] ?? '');
            if (!/^[1-9]\d*$/.test(id)) {
                throw new Error('Record saved, but the server did not return a record ID. Please check your portfolio before trying again.');
            }
            show('Created successfully. Opening the new record…', true);
            window.location.assign(form.dataset.successPath.replace('__RECORD_ID__', encodeURIComponent(id)));
        } catch (error) {
            show(error instanceof Error ? error.message : 'Unable to save this record.');
            submitting = false;
            if (submit) submit.disabled = false;
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
