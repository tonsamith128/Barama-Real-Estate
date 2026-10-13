/* BARAMA_ACCOUNT_PROFILE_EXPANSION_V1131 */
(() => {
    'use strict';
    if (location.pathname.replace(/\/+$/, '') !== '/account/settings/profile') return;

    const form = document.querySelector('[data-profile-avatar-form]');
    if (!form) return;
    const input = form.querySelector('[data-profile-avatar-input]');
    const dropzone = form.querySelector('[data-profile-avatar-dropzone]');
    const fileName = form.querySelector('[data-profile-avatar-name]');
    const submit = form.querySelector('[data-profile-avatar-submit]');
    const preview = document.querySelector('[data-profile-avatar-preview]');
    if (!input || !dropzone || !fileName || !submit) return;

    let previewUrl = '';
    const applyFile = (file) => {
        if (!file) return;
        if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
            window.alert('Choose a JPG, PNG, or WebP image.');
            input.value = ''; submit.disabled = true; return;
        }
        if (file.size > 5 * 1024 * 1024) {
            window.alert('Profile avatar must be 5 MB or smaller.');
            input.value = ''; submit.disabled = true; return;
        }
        fileName.textContent = file.name;
        submit.disabled = false;
        if (preview) {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(file);
            preview.innerHTML = '';
            const image = document.createElement('img');
            image.src = previewUrl;
            image.alt = 'Selected profile avatar preview';
            preview.append(image);
        }
    };

    input.addEventListener('change', () => applyFile(input.files?.[0]));
    ['dragenter','dragover'].forEach(type => dropzone.addEventListener(type,event => {
        event.preventDefault(); dropzone.classList.add('is-dragging');
    }));
    ['dragleave','drop'].forEach(type => dropzone.addEventListener(type,event => {
        event.preventDefault(); dropzone.classList.remove('is-dragging');
    }));
    dropzone.addEventListener('drop', event => {
        const file = event.dataTransfer?.files?.[0];
        if (!file) return;
        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
        } catch (_) {}
        applyFile(file);
    });
    dropzone.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault(); input.click();
        }
    });
    window.addEventListener('beforeunload', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    });
})();
