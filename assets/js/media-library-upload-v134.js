/* BARAMA_MEDIA_LIBRARY_UPLOAD_V134 */
(() => {
    'use strict';
    const root = document.querySelector('[data-media-upload]');
    if (!root) return;
    const form = root.querySelector('[data-media-upload-form]');
    const dropzone = root.querySelector('[data-media-dropzone]');
    const input = root.querySelector('[data-media-input]');
    const choose = root.querySelector('[data-media-choose]');
    const clear = root.querySelector('[data-media-clear]');
    const selection = root.querySelector('[data-media-selection]');
    const list = root.querySelector('[data-media-file-list]');
    const count = root.querySelector('[data-media-count]');
    const submit = root.querySelector('[data-media-submit]');
    const submitLabel = root.querySelector('[data-media-submit-label]');
    if (!form || !dropzone || !input || !selection || !list) return;

    const maxFiles = Number(form.dataset.mediaMaxFiles || 20);
    const maxBytes = Number(form.dataset.mediaMaxBytes || 52428800);
    const clientError = root.querySelector('[data-media-upload-client-error]');
    const allowed = new Set((input.accept || '').toLowerCase().split(',').map(v => v.trim()));
    const setError = (message) => {
        if (!clientError) return;
        clientError.textContent = message;
        clientError.hidden = !message;
    };
    const ext = (name) => String(name || '').split('.').pop().toLowerCase();
    const category = (file) => {
        const type = String(file.type || '').toLowerCase();
        if (type.startsWith('image/')) return 'image';
        if (type === 'application/pdf' || ext(file.name) === 'pdf') return 'pdf';
        if (type.startsWith('video/')) return 'video';
        if (type.startsWith('audio/')) return 'music';
        return 'doc';
    };
    const sizeLabel = (bytes) => Number(bytes || 0) >= 1048576
        ? `${(Number(bytes) / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Number(bytes) / 1024).toFixed(1)} KB`;

    const render = () => {
        setError('');
        const files = Array.from(input.files || []);
        list.innerHTML = '';
        selection.hidden = files.length === 0;
        if (count) count.textContent = `${files.length} file${files.length === 1 ? '' : 's'}`;
        files.forEach((file) => {
            const row = document.createElement('div');
            row.className = 'media-upload__file';
            const badge = document.createElement('span');
            badge.className = 'media-upload__file-badge';
            badge.textContent = category(file);
            const info = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = file.name;
            const mime = document.createElement('small');
            mime.textContent = file.type || 'Detected on server';
            info.append(name, mime);
            const size = document.createElement('span');
            size.textContent = sizeLabel(file.size);
            row.append(badge, info, size);
            list.append(row);
        });
    };

    const validSelection = () => {
        const files = Array.from(input.files || []);
        if (!files.length) return 'Choose at least one file to upload.';
        if (files.length > maxFiles) return `Choose no more than ${maxFiles} files per batch.`;
        for (const file of files) {
            if (!file.size || file.size > maxBytes) return `${file.name}: file is empty or exceeds the per-file limit.`;
            if (!allowed.has(`.${ext(file.name)}`)) return `${file.name}: unsupported file extension.`;
        }
        return '';
    };

    const openPicker = () => input.click();
    choose?.addEventListener('click', openPicker);
    dropzone.addEventListener('click', (event) => {
        // A programmatic input.click() bubbles back through the dropzone.
        // Ignore the native file input to prevent recursive picker invocation.
        if (event.target !== input && !event.target.closest('button')) openPicker();
    });
    dropzone.addEventListener('keydown', (event) => {
        if (event.target === dropzone && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault(); openPicker();
        }
    });
    ['dragenter', 'dragover'].forEach((name) => dropzone.addEventListener(name, (event) => {
        event.preventDefault(); dropzone.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((name) => dropzone.addEventListener(name, (event) => {
        event.preventDefault(); dropzone.classList.remove('is-dragging');
    }));
    dropzone.addEventListener('drop', (event) => {
        const files = Array.from(event.dataTransfer?.files || []);
        if (!files.length) return;
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        render();
        const error = validSelection();
        if (error) setError(error);
    });
    input.addEventListener('change', render);
    clear?.addEventListener('click', () => { input.value = ''; render(); });
    form.addEventListener('submit', (event) => {
        const error = validSelection();
        if (error) {
            event.preventDefault();
            setError(error);
            clientError?.scrollIntoView({block: 'nearest'});
            return;
        }
        setError('');
        // The global change-passcode dialog may have intercepted this submit.
        // Disable the button only when a real POST will proceed.
        if (event.defaultPrevented) return;
        if (submit) submit.disabled = true;
        if (submitLabel) submitLabel.textContent = 'Uploading…';
    });
    render();
})();
