/* BARAMA_MARKETPLACE_CONFIG_V109 */
(() => {
    'use strict';

    const form = document.querySelector('[data-marketplace-config-form-v109]');
    if (!(form instanceof HTMLFormElement)) return;

    const dialog = form.closest('dialog');
    const submit = form.querySelector('[data-marketplace-config-submit]');
    const backgroundType = form.querySelector('[data-hero-background-type]');
    const imageSource = form.querySelector('[data-marketplace-image-source]');

    const getToast = () => {
        let toast = document.querySelector('[data-marketplace-config-toast]');
        if (toast) return toast;

        toast = document.createElement('div');
        toast.className = 'barama-config-toast';
        toast.setAttribute('data-marketplace-config-toast', '');
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        document.body.appendChild(toast);
        return toast;
    };

    let toastTimer = null;
    const showToast = (message, type = 'success') => {
        const toast = getToast();
        window.clearTimeout(toastTimer);
        toast.classList.remove('is-success', 'is-error', 'is-visible');
        toast.textContent = message;
        toast.classList.add(type === 'error' ? 'is-error' : 'is-success');
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        toastTimer = window.setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 2600);
    };

    const updatePanels = () => {
        if (!(backgroundType instanceof HTMLSelectElement)) return;
        form.querySelectorAll('[data-hero-background-panel]').forEach((panel) => {
            panel.hidden = panel.getAttribute('data-hero-background-panel') !== backgroundType.value;
        });
    };

    backgroundType?.addEventListener('change', updatePanels);
    updatePanels();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const title = form.querySelector('[name="hero_title"]');
        const description = form.querySelector('[name="hero_description"]');

        if (title instanceof HTMLInputElement && title.value.trim() === '') {
            showToast('Hero headline is required.', 'error');
            title.focus();
            return;
        }

        if (description instanceof HTMLTextAreaElement && description.value.trim() === '') {
            showToast('Hero description is required.', 'error');
            description.focus();
            return;
        }

        const data = new FormData(form);
        const type = String(data.get('background_type') || 'plain');
        let source = String(data.get('image_source') || 'auto');

        if (type === 'image') {
            const url = String(data.get('image_url') || '').trim();
            const mediaUuid = String(data.get('image_media_uuid') || '').trim();
            const imageFile = data.get('hero_image_file');
            const hasUpload = imageFile instanceof File && imageFile.size > 0;

            if (hasUpload) {
                source = 'media';
                data.set('image_source', 'media');
            } else if ((source === 'url' && url === '') || (source === 'media' && mediaUuid === '')) {
                source = 'auto';
                data.set('image_source', 'auto');
                data.delete('image_url');
                data.delete('image_media_uuid');
                data.delete('hero_image_file');
            } else if (source === 'auto') {
                data.delete('image_url');
                data.delete('image_media_uuid');
                data.delete('hero_image_file');
            } else if (source === 'url') {
                data.delete('image_media_uuid');
                data.delete('hero_image_file');
            } else if (source === 'media') {
                data.delete('image_url');
            }
        } else {
            data.delete('image_source');
            data.delete('image_url');
            data.delete('image_media_uuid');
            data.delete('hero_image_file');
        }

        if (type !== 'slider') {
            data.delete('slider_existing[]');
            data.delete('slider_remove[]');
            data.delete('slider_urls');
            data.delete('hero_slider_files[]');
        }

        if (type !== 'video') {
            data.delete('video_source');
            data.delete('video_url');
            data.delete('video_media_uuid');
            data.delete('youtube_url');
            data.delete('hero_video_file');
        }

        if (submit instanceof HTMLButtonElement) {
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Barama-Marketplace-Config': 'v1.0.9'
                }
            });

            const text = await response.text();
            let payload = null;
            if (text.trim() !== '') {
                try { payload = JSON.parse(text); } catch (_) { payload = null; }
            }

            if (!response.ok || payload?.ok !== true) {
                const fallback = response.status === 419
                    ? 'Your session expired. Refresh the page and try again.'
                    : 'Configuration could not be saved.';
                throw new Error(payload?.message || fallback);
            }

            if (dialog instanceof HTMLDialogElement && dialog.open) {
                dialog.close();
            }

            showToast(payload?.data?.message || 'Configuration saved.', 'success');
            window.setTimeout(() => window.location.reload(), 850);
        } catch (error) {
            showToast(
                error instanceof Error ? error.message : 'Configuration could not be saved.',
                'error'
            );
        } finally {
            if (submit instanceof HTMLButtonElement) {
                submit.disabled = false;
                submit.removeAttribute('aria-busy');
            }
        }
    }, true);
})();
