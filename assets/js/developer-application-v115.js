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
