
(() => {
    const modal = document.querySelector('[data-invoice-template-modal]');
    const form = document.querySelector('[data-invoice-template-form]');
    if (!modal || !form) return;

    const dropzone = modal.querySelector('[data-logo-dropzone]');
    const input = modal.querySelector('[data-logo-file]');
    const browse = modal.querySelector('[data-logo-browse]');
    const clear = modal.querySelector('[data-logo-clear]');
    const status = modal.querySelector('[data-logo-upload-status]');
    const urlInput = form.querySelector('input[name="logo_url"]');
    const previewLogo = modal.querySelector('[data-preview-logo]');

    if (!dropzone || !input || !urlInput) return;

    const maxBytes = 5 * 1024 * 1024;
    const allowed = new Set([
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif',
    ]);

    let objectUrl = null;

    const setStatus = (message, state = '') => {
        if (status) {
            status.textContent = message;
            status.classList.remove('is-ready', 'is-error');
            if (state) status.classList.add(state);
        }
        clear?.classList.toggle('is-visible', state === 'is-ready');
    };

    const releaseObjectUrl = () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };

    const validate = (file) => {
        if (!file) return 'No file selected.';
        if (!allowed.has(file.type)) {
            return 'Use PNG, JPG/JPEG, WebP or GIF.';
        }
        if (file.size > maxBytes) {
            return 'Logo must be 5 MB or smaller.';
        }
        return '';
    };

    const assignFile = (file) => {
        const error = validate(file);
        if (error) {
            setStatus(error, 'is-error');
            return false;
        }

        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
        } catch (_) {
            // File picker selection already populated input.files.
        }

        releaseObjectUrl();
        objectUrl = URL.createObjectURL(file);

        if (previewLogo) {
            previewLogo.src = objectUrl;
            previewLogo.hidden = false;
        }

        setStatus(
            `${file.name} · ${(file.size / 1024).toFixed(0)} KB · ready to upload when you save`,
            'is-ready'
        );

        // Existing v1.5.0 listener on logo_url marks the form dirty.
        urlInput.dispatchEvent(new Event('change', { bubbles: true }));

        return true;
    };

    browse?.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (file) assignFile(file);
    });

    ['dragenter', 'dragover'].forEach((name) => {
        dropzone.addEventListener(name, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'dragend'].forEach((name) => {
        dropzone.addEventListener(name, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.remove('is-dragover');

        const file = event.dataTransfer?.files?.[0];
        if (file) assignFile(file);
    });

    clear?.addEventListener('click', () => {
        input.value = '';
        releaseObjectUrl();

        const currentUrl = urlInput.value.trim();
        if (previewLogo) {
            previewLogo.src = currentUrl;
            previewLogo.hidden = currentUrl === '';
        }

        setStatus(
            'No upload selected. The Logo URL / site path field is still active.'
        );
        urlInput.dispatchEvent(new Event('change', { bubbles: true }));
    });

    form.addEventListener('submit', (event) => {
        const file = input.files?.[0];
        if (!file) return;

        const error = validate(file);
        if (error) {
            event.preventDefault();
            setStatus(error, 'is-error');
        }
    });

    window.addEventListener('beforeunload', releaseObjectUrl, { once: true });
})();
