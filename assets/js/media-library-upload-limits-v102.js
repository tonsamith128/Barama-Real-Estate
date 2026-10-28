/* BARAMA_MEDIA_UPLOAD_SIZE_GUARD_V102 — no additional JavaScript dependencies. */
(() => {
    'use strict';
    const form = document.querySelector('[data-media-upload-form][data-size-file-limit]');
    if (!form) return;
    const input = form.querySelector('[data-media-input]');
    const error = form.querySelector('[data-size-error]');
    const submit = form.querySelector('[data-media-submit]');
    if (!input || !error) return;

    const maxFiles = Number(form.dataset.sizeMaxFiles || 20);
    const fileLimit = Number(form.dataset.sizeFileLimit || 0);
    const requestBudget = Number(form.dataset.sizeRequestBudget || 0);
    const size = bytes => `${(bytes / 1048576).toFixed(1)} MB`;
    let lastError = '';
    const validate = () => {
        const selected = Array.from(input.files || []);
        if (!selected.length) return 'Choose at least one file to upload.';
        if (selected.length > maxFiles) return `You selected ${selected.length} files. Maximum: ${maxFiles} files per upload.`;
        if (!Number.isFinite(fileLimit) || fileLimit < 1)
            return 'This server currently permits no uploads. Ask your administrator to check PHP upload limits.';
        const oversized = selected.find(file => !file.size || file.size > fileLimit);
        if (oversized) return `${oversized.name}: ${size(oversized.size)} exceeds this server’s per-file limit (${size(fileLimit)}). The file was NOT submitted.`;
        const total = selected.reduce((bytes, file) => bytes + file.size, 0);
        if (Number.isFinite(requestBudget) && total > requestBudget)
            return `The selected files total ${size(total)}, exceeding the server’s safe request budget (${size(requestBudget)}). Upload fewer files at once.`;
        return '';
    };
    const paint = message => {
        if (message === lastError) return;
        lastError = message;
        error.textContent = message;
        error.hidden = !message;
        error.classList.toggle('is-visible', Boolean(message));
        if (submit) submit.setAttribute('aria-describedby', message ? 'barama-upload-limit-error-v102' : '');
    };
    error.id = 'barama-upload-limit-error-v102';
    input.addEventListener('change', () => paint(input.files?.length ? validate() : ''));
    form.querySelector('[data-media-clear]')?.addEventListener('click', () => paint(''));
    form.querySelector('[data-media-dropzone]')?.addEventListener('drop', event => {
        const count = event.dataTransfer?.files?.length || 0;
        if (count > maxFiles) {
            paint(`You dropped ${count} files. Maximum: ${maxFiles} files per upload.`);
        } else {
            paint(validate());
        }
    });
    form.addEventListener('submit', event => {
        const message = validate();
        if (!message) { paint(''); return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        paint(message);
        error.scrollIntoView({behavior:'smooth',block:'nearest'});
    }, true);
})();
