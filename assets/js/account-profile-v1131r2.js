/* BARAMA_ACCOUNT_PROFILE_AVATAR_PREVIEW_V1131R2 */
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

    if (!input || !dropzone || !fileName || !submit || !preview) return;

    const acceptedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
    const maxBytes = 5 * 1024 * 1024;
    const originalPreviewMarkup = preview.innerHTML;
    let renderToken = 0;

    const restoreOriginalPreview = () => {
        preview.innerHTML = originalPreviewMarkup;
        preview.classList.remove('is-previewing');
    };

    const setReadyState = (file, canSubmit = true) => {
        fileName.textContent = file?.name || 'No file selected';
        submit.disabled = !canSubmit;
    };

    const rejectFile = (message) => {
        input.value = '';
        submit.disabled = true;
        fileName.textContent = 'No file selected';
        restoreOriginalPreview();
        window.alert(message);
    };

    const validateFile = (file) => {
        if (!file) return 'Choose a profile avatar.';
        if (!acceptedTypes.has(file.type)) return 'Choose a JPG, PNG, or WebP image.';
        if (file.size < 1) return 'The selected profile avatar is empty.';
        if (file.size > maxBytes) return 'Profile avatar must be 5 MB or smaller.';
        return '';
    };

    const drawBitmapPreview = async (file, token) => {
        if (typeof createImageBitmap !== 'function') return false;

        let bitmap;
        try {
            bitmap = await createImageBitmap(file);
        } catch (_) {
            return false;
        }

        if (token !== renderToken) {
            if (bitmap && typeof bitmap.close === 'function') bitmap.close();
            return true;
        }

        const pixelSize = 192;
        const canvas = document.createElement('canvas');
        canvas.width = pixelSize;
        canvas.height = pixelSize;
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.display = 'block';
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', 'Selected profile avatar preview');

        const context = canvas.getContext('2d', { alpha: false });
        if (!context) {
            if (bitmap && typeof bitmap.close === 'function') bitmap.close();
            return false;
        }

        const sourceWidth = bitmap.width || 0;
        const sourceHeight = bitmap.height || 0;
        if (sourceWidth < 1 || sourceHeight < 1) {
            if (bitmap && typeof bitmap.close === 'function') bitmap.close();
            return false;
        }

        const crop = Math.min(sourceWidth, sourceHeight);
        const sourceX = Math.max(0, (sourceWidth - crop) / 2);
        const sourceY = Math.max(0, (sourceHeight - crop) / 2);

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, pixelSize, pixelSize);
        context.drawImage(
            bitmap,
            sourceX, sourceY, crop, crop,
            0, 0, pixelSize, pixelSize
        );

        if (bitmap && typeof bitmap.close === 'function') bitmap.close();

        preview.replaceChildren(canvas);
        preview.classList.add('is-previewing');
        return true;
    };

    const drawFileReaderFallback = (file, token) => new Promise((resolve) => {
        if (typeof FileReader !== 'function') {
            resolve(false);
            return;
        }

        const reader = new FileReader();

        reader.onerror = () => resolve(false);
        reader.onload = () => {
            if (token !== renderToken || typeof reader.result !== 'string') {
                resolve(token !== renderToken);
                return;
            }

            const image = new Image();
            image.decoding = 'async';

            image.onload = () => {
                if (token !== renderToken) {
                    resolve(true);
                    return;
                }

                image.alt = '';
                preview.replaceChildren(image);
                preview.classList.add('is-previewing');
                resolve(true);
            };

            image.onerror = () => resolve(false);
            image.src = reader.result;
        };

        reader.readAsDataURL(file);
    });

    const renderPreview = async (file) => {
        const token = ++renderToken;

        preview.classList.add('is-loading');
        preview.setAttribute('aria-busy', 'true');

        let rendered = false;

        try {
            rendered = await drawBitmapPreview(file, token);
            if (!rendered) {
                rendered = await drawFileReaderFallback(file, token);
            }
        } finally {
            if (token === renderToken) {
                preview.classList.remove('is-loading');
                preview.removeAttribute('aria-busy');

                if (!rendered) {
                    restoreOriginalPreview();
                    fileName.textContent = `${file.name} · preview unavailable, ready to upload`;
                }
            }
        }
    };

    const applySelectedFile = (file, inputBacked) => {
        const problem = validateFile(file);
        if (problem) {
            rejectFile(problem);
            return;
        }

        setReadyState(file, inputBacked);
        renderPreview(file);

        if (!inputBacked) {
            fileName.textContent = `${file.name} · click to choose before saving`;
        }
    };

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            ++renderToken;
            restoreOriginalPreview();
            setReadyState(null, false);
            return;
        }

        applySelectedFile(file, true);
    });

    ['dragenter', 'dragover'].forEach((type) => {
        dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragleave', 'drop'].forEach((type) => {
        dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const file = event.dataTransfer?.files?.[0];
        if (!file) return;

        const problem = validateFile(file);
        if (problem) {
            rejectFile(problem);
            return;
        }

        let inputBacked = false;

        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;

            const attached = input.files?.[0];
            inputBacked = Boolean(
                attached
                && attached.name === file.name
                && attached.size === file.size
                && attached.type === file.type
            );
        } catch (_) {
            inputBacked = false;
        }

        applySelectedFile(file, inputBacked);
    });

    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            input.click();
        }
    });

    // Never submit an avatar form unless the native file input really carries a file.
    form.addEventListener('submit', (event) => {
        const file = input.files?.[0];
        const problem = validateFile(file);

        if (problem) {
            event.preventDefault();
            submit.disabled = true;
            window.alert(problem);
        }
    });
})();
