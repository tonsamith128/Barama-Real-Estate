/* BARAMA_MEDIA_LIBRARY_INDEX_V137 */
(() => {
    'use strict';

    let modal = null;
    let stage = null;
    let title = null;
    let meta = null;
    let openOriginal = null;
    let note = null;
    let lastTrigger = null;

    const clearStage = () => {
        if (!stage) return;

        stage.querySelectorAll('video, audio').forEach((media) => {
            try {
                media.pause();
                media.removeAttribute('src');
                media.load();
            } catch (_) {
                // Browser-specific media teardown differences are harmless.
            }
        });

        stage.replaceChildren();
    };

    const hide = () => {
        if (!modal) return;

        clearStage();
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('workspace-media-preview-open');

        if (lastTrigger) {
            lastTrigger.focus({preventScroll: true});
        }

        lastTrigger = null;
    };

    const ensureModal = () => {
        if (modal) return;

        modal = document.createElement('div');
        modal.className = 'workspace-media-modal';
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        const backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.className = 'workspace-media-modal__backdrop';
        backdrop.setAttribute('aria-label', 'Close media preview');

        const dialog = document.createElement('section');
        dialog.className = 'workspace-media-modal__dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute(
            'aria-labelledby',
            'workspace-media-preview-title'
        );

        const header = document.createElement('header');
        header.className = 'workspace-media-modal__header';

        const headingGroup = document.createElement('div');

        const eyebrow = document.createElement('span');
        eyebrow.className = 'workspace-media-modal__eyebrow';
        eyebrow.textContent = 'Media Library Preview';

        title = document.createElement('h2');
        title.id = 'workspace-media-preview-title';
        title.textContent = 'Media preview';

        meta = document.createElement('p');

        headingGroup.append(eyebrow, title, meta);

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'workspace-media-modal__close';
        close.setAttribute('aria-label', 'Close media preview');
        close.textContent = '×';

        header.append(headingGroup, close);

        stage = document.createElement('div');
        stage.className = 'workspace-media-modal__stage';

        const footer = document.createElement('footer');
        footer.className = 'workspace-media-modal__footer';

        note = document.createElement('span');
        note.textContent = 'Preview is available only to authorized Media Library administrators.';

        openOriginal = document.createElement('a');
        openOriginal.className = 'btn btn-primary';
        openOriginal.href = '#';
        openOriginal.target = '_blank';
        openOriginal.rel = 'noopener';
        openOriginal.textContent = 'Open original';

        footer.append(note, openOriginal);
        dialog.append(header, stage, footer);
        modal.append(backdrop, dialog);
        document.body.append(modal);

        backdrop.addEventListener('click', hide);
        close.addEventListener('click', hide);
    };

    const makeInfoPanel = (kind, name, mime, url) => {
        const wrapper = document.createElement('div');
        wrapper.className = kind === 'audio'
            ? 'workspace-media-modal__audio'
            : 'workspace-media-modal__document';

        const mark = document.createElement('span');
        mark.className = 'workspace-media-modal__mark';
        mark.textContent = kind === 'audio' ? 'Audio' : 'File';

        const strong = document.createElement('strong');
        strong.textContent = name;

        const description = document.createElement('p');
        description.textContent = kind === 'audio'
            ? 'Listen to this protected Media Library audio asset.'
            : `Inline browser preview is not available for ${mime || 'this document type'}. Use Open original to view or download it.`;

        wrapper.append(mark, strong, description);

        if (kind === 'audio') {
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.preload = 'metadata';
            audio.src = url;
            wrapper.append(audio);
        }

        return wrapper;
    };

    const render = (trigger) => {
        ensureModal();

        const url = trigger.dataset.mediaUrl || '';
        const name = trigger.dataset.mediaName || 'Media preview';
        const mime = trigger.dataset.mediaMime || '';
        const kind = trigger.dataset.mediaKind || 'document';
        const alt = trigger.dataset.mediaAlt || '';
        const dimensions = trigger.dataset.mediaDimensions || '';

        clearStage();

        title.textContent = name;
        meta.textContent = [mime, dimensions].filter(Boolean).join(' · ');
        openOriginal.href = url;
        note.textContent = kind === 'document'
            ? 'This protected file will use your browser’s document handling.'
            : 'Preview is available only through the authenticated Admin Media Library route.';

        let content;

        if (kind === 'image') {
            content = document.createElement('img');
            content.src = url;
            content.alt = alt || name;
            content.decoding = 'async';
        } else if (kind === 'video') {
            content = document.createElement('video');
            content.src = url;
            content.controls = true;
            content.preload = 'metadata';
            content.playsInline = true;
        } else if (kind === 'pdf') {
            content = document.createElement('iframe');
            content.src = url;
            content.title = `Preview ${name}`;
        } else {
            content = makeInfoPanel(kind, name, mime, url);
        }

        stage.append(content);
    };

    const show = (trigger) => {
        lastTrigger = trigger;
        render(trigger);

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('workspace-media-preview-open');

        modal
            .querySelector('.workspace-media-modal__close')
            ?.focus({preventScroll: true});
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-media-preview]');
        if (!trigger) return;

        event.preventDefault();
        show(trigger);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            hide();
        }
    });
})();
