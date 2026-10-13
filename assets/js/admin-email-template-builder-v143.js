/* BARAMA_ADMIN_EMAIL_TEMPLATE_BUILDER_V143 */
(() => {
    'use strict';

    const root = document.querySelector('[data-email-template-builder][data-email-template-version="143"]');
    if (!root) return;

    const form = root.querySelector('[data-email-builder-form]');
    const nameField = root.querySelector('[data-email-name]');
    const description = root.querySelector('[data-email-description]');
    const subject = root.querySelector('[data-email-subject]');
    const html = root.querySelector('[data-email-html]');
    const rich = root.querySelector('[data-email-rich-editor]');
    const text = root.querySelector('[data-email-text]');
    const customKeyField = root.querySelector('[data-email-custom-key]');
    const createForm = root.querySelector('[data-email-create-form]');
    const createMode = root.getAttribute('data-email-create-mode') === '1';
    const sampleNode = root.querySelector('[data-email-template-samples]');
    const defaultsNode = root.querySelector('[data-email-template-defaults]');
    const previewSubject = root.querySelector('[data-email-preview-subject]');
    const previewHtml = root.querySelector('[data-email-preview-html]');
    const previewText = root.querySelector('[data-email-preview-text]');
    const htmlCount = root.querySelector('[data-email-html-count]');
    const textCount = root.querySelector('[data-email-text-count]');
    const notice = root.querySelector('[data-workspace-notice]');
    const testRecipient = root.querySelector('[data-email-test-recipient]');
    const testStatus = root.querySelector('[data-email-test-status]');
    const revisionList = root.querySelector('[data-email-revision-list]');
    const toolbar = root.querySelector('.email-builder__quick-toolbar');
    const sourceToggle = root.querySelector('[data-email-source-toggle]');
    const editorShell = root.querySelector('[data-email-editor-shell]');
    const mediaLibraryUrl = root.getAttribute('data-email-media-library-url') || '';
    const templateKey = root.getAttribute('data-email-template-key') || 'unknown';
    const testUrl = root.getAttribute('data-email-test-url') || '';
    const templateLocale = root.getAttribute('data-email-template-locale') || 'en';
    const editable = ['new', 'fallback', 'inactive'].includes(root.getAttribute('data-email-template-status') || '');
    const revisionStorageKey = `barama.email-builder.revisions.${templateKey}.${templateLocale}`;

    let samples = {};
    let defaults = {};
    let lastField = rich || subject || text || html;
    let savedRange = null;
    let activeBlock = null;
    let sourceMode = false;
    let history = [];
    let historyIndex = -1;
    let historyTimer = null;
    let applyingHistory = false;
    let dialogMode = '';
    let selectedMedia = null;
    let mediaSearchTimer = null;

    try { samples = JSON.parse(sampleNode?.textContent || '{}'); } catch (_) { samples = {}; }
    try { defaults = JSON.parse(defaultsNode?.textContent || '{}'); } catch (_) { defaults = {}; }

    const showNotice = (message, type = 'success') => {
        if (!notice) return;
        notice.hidden = false;
        notice.classList.remove('is-success', 'is-error');
        notice.classList.add(type === 'error' ? 'is-error' : 'is-success');
        notice.textContent = message;
        window.setTimeout(() => {
            if (notice.textContent === message) notice.hidden = true;
        }, 7000);
    };

    const normalizeCustomKey = (value) => {
        let suffix = String(value || '').toLowerCase().trim();
        suffix = suffix.replace(/^custom_+/, '');
        suffix = suffix.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/_+/g, '_');
        return `custom_${suffix}`.slice(0, 100);
    };

    let customKeyTouched = false;
    if (createMode && customKeyField) {
        customKeyField.addEventListener('input', () => { customKeyTouched = true; });
        customKeyField.addEventListener('blur', () => {
            customKeyField.value = normalizeCustomKey(customKeyField.value);
        });
        nameField?.addEventListener('input', () => {
            if (customKeyTouched && customKeyField.value !== 'custom_') return;
            const generated = normalizeCustomKey(nameField.value);
            customKeyField.value = generated === 'custom_' ? 'custom_' : generated;
        });
    }

    const replaceVariables = (value) => String(value || '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key) => {
        const normalized = String(key || '').toLowerCase();
        return Object.prototype.hasOwnProperty.call(samples, normalized) ? String(samples[normalized]) : '';
    });

    const syncRichToHtml = () => {
        if (!rich || !html || sourceMode) return;
        html.value = rich.innerHTML.trim();
    };

    const syncHtmlToRich = () => {
        if (!rich || !html) return;
        rich.innerHTML = html.value || '';
    };

    const refresh = () => {
        if (!sourceMode) syncRichToHtml();
        if (previewSubject) previewSubject.textContent = replaceVariables(subject?.value || '');
        if (previewHtml) previewHtml.srcdoc = replaceVariables(html?.value || '');
        if (previewText) previewText.textContent = replaceVariables(text?.value || '');
        if (htmlCount) htmlCount.textContent = `${(html?.value || '').length.toLocaleString()} / 20,000`;
        if (textCount) textCount.textContent = `${(text?.value || '').length.toLocaleString()} / 10,000`;
    };

    const captureState = () => {
        if (!sourceMode) syncRichToHtml();
        return {
            name: nameField?.value || '',
            description: description?.value || '',
            subject: subject?.value || '',
            html: html?.value || '',
            text: text?.value || '',
        };
    };

    const stateSignature = (state) => JSON.stringify(state);

    const applyState = (state) => {
        applyingHistory = true;
        if (nameField) nameField.value = String(state?.name ?? '');
        if (description) description.value = String(state?.description ?? '');
        if (subject) subject.value = String(state?.subject ?? '');
        if (html) html.value = String(state?.html ?? '');
        if (text) text.value = String(state?.text ?? '');
        syncHtmlToRich();
        refresh();
        applyingHistory = false;
    };

    const pushHistory = (state = captureState()) => {
        if (!editable || applyingHistory) return;
        const signature = stateSignature(state);
        if (historyIndex >= 0 && stateSignature(history[historyIndex]) === signature) return;
        history = history.slice(0, historyIndex + 1);
        history.push(state);
        if (history.length > 60) history.shift();
        historyIndex = history.length - 1;
    };

    const scheduleHistory = () => {
        if (!editable || applyingHistory) return;
        window.clearTimeout(historyTimer);
        historyTimer = window.setTimeout(() => pushHistory(), 320);
    };

    const undo = () => {
        window.clearTimeout(historyTimer);
        if (historyIndex <= 0) return;
        historyIndex -= 1;
        applyState(history[historyIndex]);
    };

    const redo = () => {
        window.clearTimeout(historyTimer);
        if (historyIndex < 0 || historyIndex >= history.length - 1) return;
        historyIndex += 1;
        applyState(history[historyIndex]);
    };

    const selectionInsideRich = (range) => {
        if (!rich || !range) return false;
        const container = range.commonAncestorContainer;
        return container === rich || rich.contains(container.nodeType === Node.TEXT_NODE ? container.parentNode : container);
    };

    const saveEditorSelection = () => {
        if (!rich) return;
        const selection = window.getSelection();
        if (!selection || selection.rangeCount < 1) return;
        const range = selection.getRangeAt(0);
        if (!selectionInsideRich(range)) return;
        savedRange = range.cloneRange();
    };

    const restoreEditorSelection = () => {
        if (!rich) return false;
        const selection = window.getSelection();
        if (!selection) return false;
        selection.removeAllRanges();
        if (savedRange && selectionInsideRich(savedRange)) {
            selection.addRange(savedRange);
            return true;
        }
        const range = document.createRange();
        range.selectNodeContents(rich);
        range.collapse(false);
        selection.addRange(range);
        savedRange = range.cloneRange();
        return true;
    };

    document.addEventListener('selectionchange', () => {
        if (document.activeElement === rich || rich?.contains(document.activeElement)) {
            saveEditorSelection();
        }
    });

    const topLevelBlockFromNode = (node) => {
        if (!rich || !node) return null;
        let current = node.nodeType === Node.TEXT_NODE ? node.parentNode : node;
        while (current && current !== rich && current.parentNode !== rich) current = current.parentNode;
        return current && current.parentNode === rich && current.nodeType === Node.ELEMENT_NODE ? current : null;
    };

    rich?.addEventListener('click', (event) => {
        activeBlock = topLevelBlockFromNode(event.target);
        lastField = rich;
        saveEditorSelection();
    });
    rich?.addEventListener('keyup', () => {
        const selection = window.getSelection();
        activeBlock = selection?.anchorNode ? topLevelBlockFromNode(selection.anchorNode) : activeBlock;
        lastField = rich;
        saveEditorSelection();
    });
    rich?.addEventListener('input', () => {
        lastField = rich;
        syncRichToHtml();
        refresh();
        scheduleHistory();
    });
    rich?.addEventListener('focus', () => { lastField = rich; saveEditorSelection(); });

    [nameField, description, subject, text, html].forEach((field) => {
        field?.addEventListener('focus', () => { lastField = field; });
        field?.addEventListener('input', () => {
            if (field === html && !sourceMode) return;
            if (field === html && sourceMode) syncHtmlToRich();
            refresh();
            scheduleHistory();
        });
    });

    root.querySelectorAll('[data-email-builder-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-email-builder-tab');
            root.querySelectorAll('[data-email-builder-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
            root.querySelectorAll('[data-email-builder-panel]').forEach((panel) => {
                panel.hidden = panel.getAttribute('data-email-builder-panel') !== target;
            });
        });
    });

    const insertAtTextField = (field, value) => {
        if (!field || field.readOnly || field.disabled) return;
        const start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
        const end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
        field.setRangeText(value, start, end, 'end');
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const insertHtmlAtSelection = (markup) => {
        if (!editable || !rich || sourceMode) return;
        rich.focus();
        restoreEditorSelection();
        pushHistory();

        let inserted = false;
        try {
            inserted = document.execCommand('insertHTML', false, markup);
        } catch (_) {
            inserted = false;
        }

        if (!inserted) {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount < 1) return;
            const range = selection.getRangeAt(0);
            range.deleteContents();
            const template = document.createElement('template');
            template.innerHTML = markup;
            const fragment = template.content.cloneNode(true);
            const last = fragment.lastChild;
            range.insertNode(fragment);
            if (last) {
                range.setStartAfter(last);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }

        saveEditorSelection();
        syncRichToHtml();
        refresh();
        pushHistory();
    };

    const insertTextAtEditor = (value) => {
        if (!editable || !rich || sourceMode) return;
        rich.focus();
        restoreEditorSelection();
        try {
            document.execCommand('insertText', false, value);
        } catch (_) {
            insertHtmlAtSelection(escapeHtml(value));
            return;
        }
        saveEditorSelection();
        syncRichToHtml();
        refresh();
        scheduleHistory();
    };

    const executeEditorCommand = (command, value = null) => {
        if (!editable || !rich || sourceMode) return;
        rich.focus();
        restoreEditorSelection();
        pushHistory();
        try { document.execCommand(command, false, value); } catch (_) { return; }
        saveEditorSelection();
        syncRichToHtml();
        refresh();
        pushHistory();
    };

    toolbar?.querySelectorAll('button, label').forEach((control) => {
        control.addEventListener('pointerdown', () => saveEditorSelection());
    });

    root.querySelectorAll('[data-email-history]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            button.getAttribute('data-email-history') === 'undo' ? undo() : redo();
        });
    });

    root.querySelectorAll('[data-email-command]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => executeEditorCommand(button.getAttribute('data-email-command') || 'bold'));
    });

    root.querySelectorAll('[data-email-format]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const tag = String(button.getAttribute('data-email-format') || 'p').toUpperCase();
            executeEditorCommand('formatBlock', tag);
        });
    });

    root.querySelectorAll('[data-email-align]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const align = button.getAttribute('data-email-align') || 'left';
            const command = align === 'center' ? 'justifyCenter' : (align === 'right' ? 'justifyRight' : 'justifyLeft');
            executeEditorCommand(command);
        });
    });

    root.querySelector('[data-email-text-color]')?.addEventListener('input', (event) => {
        executeEditorCommand('foreColor', event.currentTarget.value || '#1f2937');
    });
    root.querySelector('[data-email-background-color]')?.addEventListener('input', (event) => {
        const color = event.currentTarget.value || '#fff3bf';
        if (!editable || sourceMode) return;
        rich?.focus();
        restoreEditorSelection();
        pushHistory();
        let applied = false;
        try { applied = document.execCommand('hiliteColor', false, color); } catch (_) { applied = false; }
        if (!applied) {
            try { document.execCommand('backColor', false, color); } catch (_) { /* no-op */ }
        }
        saveEditorSelection();
        syncRichToHtml();
        refresh();
        pushHistory();
    });

    root.querySelector('[data-email-font-size]')?.addEventListener('change', (event) => {
        if (!editable || sourceMode || !rich) return;
        const size = Math.max(10, Math.min(40, Number(event.currentTarget.value || 16)));
        rich.focus();
        restoreEditorSelection();
        pushHistory();
        try { document.execCommand('fontSize', false, '7'); } catch (_) { return; }
        rich.querySelectorAll('font[size="7"]').forEach((font) => {
            const span = document.createElement('span');
            span.style.fontSize = `${size}px`;
            span.style.lineHeight = '1.55';
            while (font.firstChild) span.appendChild(font.firstChild);
            font.replaceWith(span);
        });
        saveEditorSelection();
        syncRichToHtml();
        refresh();
        pushHistory();
    });

    const setSourceMode = (enabled) => {
        sourceMode = Boolean(enabled);
        if (!rich || !html || !editorShell) return;

        if (sourceMode) {
            syncRichToHtml();
            rich.hidden = true;
            html.hidden = false;
            editorShell.classList.add('is-source-mode');
            sourceToggle?.classList.add('is-active');
            sourceToggle?.setAttribute('aria-pressed', 'true');
            html.focus();
            lastField = html;
        } else {
            syncHtmlToRich();
            html.hidden = true;
            rich.hidden = false;
            editorShell.classList.remove('is-source-mode');
            sourceToggle?.classList.remove('is-active');
            sourceToggle?.setAttribute('aria-pressed', 'false');
            rich.focus();
            lastField = rich;
            refresh();
        }

        toolbar?.querySelectorAll('button:not([data-email-source-toggle]), input, select').forEach((control) => {
            control.disabled = sourceMode || !editable;
        });
    };

    sourceToggle?.addEventListener('click', () => setSourceMode(!sourceMode));
    sourceToggle?.setAttribute('aria-pressed', 'false');

    root.querySelectorAll('[data-email-variable]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const value = button.getAttribute('data-email-variable') || '';
            if (lastField === rich && !sourceMode) {
                insertTextAtEditor(value);
            } else if (lastField === html && sourceMode) {
                insertAtTextField(html, value);
            } else {
                insertAtTextField(lastField, value);
            }
        });
    });

    root.querySelectorAll('[data-email-insert-block]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const content = button.getAttribute('data-email-block-html') || '';
            if (sourceMode) {
                insertAtTextField(html, `\n${content}\n`);
            } else {
                insertHtmlAtSelection(content);
            }
        });
    });

    const currentBlock = () => {
        if (activeBlock && activeBlock.parentNode === rich) return activeBlock;
        const selection = window.getSelection();
        if (selection?.anchorNode) return topLevelBlockFromNode(selection.anchorNode);
        return null;
    };

    const blockAction = (action) => {
        if (!editable || sourceMode || !rich) return;
        const block = currentBlock();
        if (!block) {
            showNotice('Place the cursor inside a Text Editor block first.', 'error');
            return;
        }
        pushHistory();
        if (action === 'duplicate') {
            const clone = block.cloneNode(true);
            block.after(clone);
            activeBlock = clone;
        } else if (action === 'delete') {
            const next = block.nextElementSibling || block.previousElementSibling;
            block.remove();
            activeBlock = next || null;
        } else if (action === 'up') {
            const previous = block.previousElementSibling;
            if (!previous) return showNotice('This block is already first.', 'error');
            previous.before(block);
        } else if (action === 'down') {
            const next = block.nextElementSibling;
            if (!next) return showNotice('This block is already last.', 'error');
            next.after(block);
        }
        syncRichToHtml();
        refresh();
        pushHistory();
    };

    root.querySelectorAll('[data-email-block-action]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => blockAction(button.getAttribute('data-email-block-action') || ''));
    });

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const escapeAttr = escapeHtml;

    const allowedEmailUrl = (value) => {
        const url = String(value || '').trim();
        if (/^https?:\/\//i.test(url)) return url;
        if (/^\{\{\s*app_url\s*\}\}(?:\/|$)/i.test(url)) return url;
        return '';
    };

    const photoMarkup = (url, alt, clickUrl = '') => {
        const src = allowedEmailUrl(url);
        if (!src) throw new Error('Enter an absolute http(s) image URL or use {{app_url}}.');
        const click = clickUrl ? allowedEmailUrl(clickUrl) : '';
        if (clickUrl && !click) throw new Error('The optional click URL must use http(s) or {{app_url}}.');
        const image = `<img src="${escapeAttr(src)}" alt="${escapeAttr(alt || '')}" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;outline:none;text-decoration:none;margin:0 auto;">`;
        const content = click ? `<a href="${escapeAttr(click)}" style="text-decoration:none;border:0;">${image}</a>` : image;
        return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:18px 0;"><tr><td align="center" style="padding:0;">${content}</td></tr></table>`;
    };

    const videoMarkup = (url, label, poster = '') => {
        const href = allowedEmailUrl(url);
        if (!href) throw new Error('Enter an absolute http(s) video URL.');
        const safeLabel = escapeHtml(label || 'Watch video');
        const posterUrl = poster ? allowedEmailUrl(poster) : '';
        if (poster && !posterUrl) throw new Error('Poster image URL must use http(s) or {{app_url}}.');
        const visual = posterUrl
            ? `<a href="${escapeAttr(href)}" style="text-decoration:none;border:0;"><img src="${escapeAttr(posterUrl)}" alt="${escapeAttr(label || 'Watch video')}" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;outline:none;margin:0 auto;"></a>`
            : `<a href="${escapeAttr(href)}" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;">▶ ${safeLabel}</a>`;
        const caption = posterUrl ? `<div style="padding-top:10px;"><a href="${escapeAttr(href)}" style="color:#2563eb;text-decoration:underline;font-weight:700;">▶ ${safeLabel}</a></div>` : '';
        return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:18px 0;"><tr><td align="center" style="padding:0;">${visual}${caption}</td></tr></table>`;
    };

    const youtubeId = (value) => {
        const url = String(value || '').trim();
        const patterns = [
            /youtu\.be\/([A-Za-z0-9_-]{6,})/i,
            /youtube\.com\/watch\?(?:.*&)?v=([A-Za-z0-9_-]{6,})/i,
            /youtube\.com\/(?:embed|shorts)\/([A-Za-z0-9_-]{6,})/i,
        ];
        for (const pattern of patterns) {
            const match = url.match(pattern);
            if (match?.[1]) return match[1];
        }
        return '';
    };

    const youtubeMarkup = (url, label) => {
        const id = youtubeId(url);
        if (!id) throw new Error('Enter a valid YouTube video URL.');
        const href = `https://www.youtube.com/watch?v=${encodeURIComponent(id)}`;
        const poster = `https://i.ytimg.com/vi/${encodeURIComponent(id)}/hqdefault.jpg`;
        return videoMarkup(href, label || 'Watch on YouTube', poster);
    };

    const linkMarkup = (url, label) => {
        const href = allowedEmailUrl(url);
        if (!href) throw new Error('Enter an absolute http(s) URL or use {{app_url}}.');
        return `<a href="${escapeAttr(href)}" style="color:#2563eb;text-decoration:underline;">${escapeHtml(label || href)}</a>`;
    };

    const dialog = root.querySelector('[data-email-insert-dialog]');
    const dialogTitle = root.querySelector('[data-email-dialog-title]');
    const dialogHelp = root.querySelector('[data-email-dialog-help]');
    const dialogForm = root.querySelector('[data-email-dialog-form]');
    const dialogInsert = root.querySelector('[data-email-dialog-insert]');
    const dialogUrl = root.querySelector('[data-email-dialog-url]');
    const dialogUrlLabel = root.querySelector('[data-email-dialog-url-label]');
    const dialogLabel = root.querySelector('[data-email-dialog-label]');
    const dialogAlt = root.querySelector('[data-email-dialog-alt]');
    const dialogPoster = root.querySelector('[data-email-dialog-poster]');
    const dialogClick = root.querySelector('[data-email-dialog-click]');
    const mediaLibrary = root.querySelector('[data-email-media-library]');
    const mediaSearch = root.querySelector('[data-email-media-search]');
    const mediaType = root.querySelector('[data-email-media-type]');
    const mediaGrid = root.querySelector('[data-email-media-grid]');
    const mediaStatus = root.querySelector('[data-email-media-status]');

    const setDialogField = (name, visible) => {
        const field = root.querySelector(`[data-email-dialog-field="${name}"]`);
        if (field) field.hidden = !visible;
    };

    const resetDialogFields = () => {
        [dialogUrl, dialogLabel, dialogAlt, dialogPoster, dialogClick].forEach((field) => { if (field) field.value = ''; });
        selectedMedia = null;
        mediaGrid?.querySelectorAll('.is-selected').forEach((item) => item.classList.remove('is-selected'));
    };

    const selectedEditorText = () => {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount < 1) return '';
        const range = selection.getRangeAt(0);
        return selectionInsideRich(range) ? selection.toString().trim() : '';
    };

    const openDialog = (mode) => {
        if (!dialog || !editable) return;
        saveEditorSelection();
        dialogMode = mode;
        resetDialogFields();
        dialog.hidden = false;
        dialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('email-builder-dialog-open');
        if (dialogForm) dialogForm.hidden = mode === 'media';
        if (mediaLibrary) mediaLibrary.hidden = mode !== 'media';
        if (dialogInsert) dialogInsert.disabled = mode === 'media';

        const config = {
            photo: ['Insert photo', 'Use an absolute image URL. The editor generates responsive email-safe image markup.', 'Image URL'],
            video: ['Insert video', 'Email clients do not reliably play embedded video. Barama inserts a linked poster/card instead.', 'Video URL'],
            youtube: ['Insert YouTube', 'YouTube is inserted as a clickable thumbnail linked to the video, not an iframe.', 'YouTube URL'],
            link: ['Insert link', 'Add a standard email-safe link at the current cursor position.', 'Link URL'],
            media: ['Media Library', 'Choose an existing Barama image or video. Video assets are inserted as email-safe links.', 'URL'],
        }[mode] || ['Insert content', 'Add email-safe content.', 'URL'];

        if (dialogTitle) dialogTitle.textContent = config[0];
        if (dialogHelp) dialogHelp.textContent = config[1];
        if (dialogUrlLabel) dialogUrlLabel.textContent = config[2];

        setDialogField('url', ['photo', 'video', 'youtube', 'link'].includes(mode));
        setDialogField('label', ['video', 'youtube', 'link'].includes(mode));
        setDialogField('alt', mode === 'photo');
        setDialogField('poster', mode === 'video');
        setDialogField('click', mode === 'photo');

        if (mode === 'link' && dialogLabel) dialogLabel.value = selectedEditorText();
        if (mode === 'media') {
            if (mediaSearch) mediaSearch.value = '';
            if (mediaType) mediaType.value = 'all';
            loadMediaLibrary();
            mediaSearch?.focus();
        } else {
            window.setTimeout(() => dialogUrl?.focus(), 30);
        }
    };

    const closeDialog = () => {
        if (!dialog) return;
        dialog.hidden = true;
        dialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('email-builder-dialog-open');
        dialogMode = '';
        selectedMedia = null;
        if (rich && !sourceMode) {
            rich.focus();
            restoreEditorSelection();
        }
    };

    root.querySelectorAll('[data-email-dialog-close]').forEach((button) => button.addEventListener('click', closeDialog));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog && !dialog.hidden) closeDialog();
    });

    const renderMediaLibrary = (items) => {
        if (!mediaGrid || !mediaStatus) return;
        mediaGrid.replaceChildren();
        selectedMedia = null;
        if (dialogInsert) dialogInsert.disabled = true;
        if (!Array.isArray(items) || items.length === 0) {
            mediaStatus.hidden = false;
            mediaStatus.textContent = 'No image or video assets match this search.';
            return;
        }
        mediaStatus.hidden = true;
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'email-builder__media-item';
            const preview = document.createElement('span');
            preview.className = 'email-builder__media-item-preview';
            if (item.media_type === 'image') {
                const image = document.createElement('img');
                image.src = String(item.url || '');
                image.alt = String(item.alt_text || item.name || '');
                image.loading = 'lazy';
                preview.appendChild(image);
            } else {
                preview.classList.add('is-video');
                preview.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/></svg>';
            }
            const meta = document.createElement('span');
            meta.className = 'email-builder__media-item-meta';
            const name = document.createElement('strong');
            name.textContent = String(item.name || 'Media asset');
            const type = document.createElement('small');
            type.textContent = String(item.mime_type || item.media_type || 'media');
            meta.append(name, type);
            button.append(preview, meta);
            button.addEventListener('click', () => {
                mediaGrid.querySelectorAll('.is-selected').forEach((entry) => entry.classList.remove('is-selected'));
                button.classList.add('is-selected');
                selectedMedia = item;
                if (dialogInsert) dialogInsert.disabled = false;
            });
            mediaGrid.appendChild(button);
        });
    };

    async function loadMediaLibrary() {
        if (!mediaLibraryUrl || !mediaStatus) {
            if (mediaStatus) mediaStatus.textContent = 'Media Library endpoint is unavailable.';
            return;
        }
        mediaStatus.hidden = false;
        mediaStatus.textContent = 'Loading media…';
        if (mediaGrid) mediaGrid.replaceChildren();
        const query = String(mediaSearch?.value || '').trim();
        const type = String(mediaType?.value || 'all');
        const url = `${mediaLibraryUrl}?q=${encodeURIComponent(query)}&type=${encodeURIComponent(type)}`;
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || payload?.ok !== true) {
                throw new Error(payload?.message || `Media Library failed with HTTP ${response.status}.`);
            }
            renderMediaLibrary(payload?.data?.items || []);
        } catch (error) {
            mediaStatus.hidden = false;
            mediaStatus.textContent = error instanceof Error ? error.message : 'Media Library could not be loaded.';
        }
    }

    mediaSearch?.addEventListener('input', () => {
        window.clearTimeout(mediaSearchTimer);
        mediaSearchTimer = window.setTimeout(loadMediaLibrary, 260);
    });
    mediaType?.addEventListener('change', loadMediaLibrary);

    root.querySelectorAll('[data-email-insert-tool]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const mode = button.getAttribute('data-email-insert-tool') || '';
            if (mode === 'text') {
                insertHtmlAtSelection('<p style="margin:0 0 16px;line-height:1.65;">Write your text here.</p>');
                return;
            }
            openDialog(mode);
        });
    });

    dialogInsert?.addEventListener('click', () => {
        try {
            let markup = '';
            if (dialogMode === 'photo') {
                markup = photoMarkup(dialogUrl?.value || '', dialogAlt?.value || '', dialogClick?.value || '');
            } else if (dialogMode === 'video') {
                markup = videoMarkup(dialogUrl?.value || '', dialogLabel?.value || 'Watch video', dialogPoster?.value || '');
            } else if (dialogMode === 'youtube') {
                markup = youtubeMarkup(dialogUrl?.value || '', dialogLabel?.value || 'Watch on YouTube');
            } else if (dialogMode === 'link') {
                markup = linkMarkup(dialogUrl?.value || '', dialogLabel?.value || 'Open link');
            } else if (dialogMode === 'media') {
                if (!selectedMedia) throw new Error('Choose an item from the Media Library first.');
                markup = selectedMedia.media_type === 'video'
                    ? videoMarkup(String(selectedMedia.url || ''), String(selectedMedia.name || 'Watch video'), '')
                    : photoMarkup(String(selectedMedia.url || ''), String(selectedMedia.alt_text || selectedMedia.name || ''), '');
            }
            if (!markup) throw new Error('Nothing was selected to insert.');
            closeDialog();
            insertHtmlAtSelection(markup);
        } catch (error) {
            showNotice(error instanceof Error ? error.message : 'Content could not be inserted.', 'error');
        }
    });

    root.querySelectorAll('[data-email-preview-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.getAttribute('data-email-preview-mode');
            root.querySelectorAll('[data-email-preview-mode]').forEach((item) => item.classList.toggle('is-active', item === button));
            root.querySelector('[data-email-preview-frame]')?.classList.toggle('is-mobile', mode === 'mobile');
        });
    });

    const loadRevisions = () => {
        try {
            const parsed = JSON.parse(localStorage.getItem(revisionStorageKey) || '[]');
            return Array.isArray(parsed) ? parsed.slice(0, 12) : [];
        } catch (_) {
            return [];
        }
    };

    const saveRevisions = (revisions) => {
        try { localStorage.setItem(revisionStorageKey, JSON.stringify(revisions.slice(0, 12))); } catch (_) { /* storage may be disabled */ }
    };

    const renderRevisions = () => {
        if (!revisionList) return;
        const revisions = loadRevisions();
        revisionList.replaceChildren();
        if (!revisions.length) {
            const empty = document.createElement('p');
            empty.textContent = 'No browser snapshots yet.';
            revisionList.append(empty);
            return;
        }
        revisions.forEach((revision, index) => {
            const row = document.createElement('div');
            row.className = 'email-builder__revision-row';
            const meta = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = revision.label || `Snapshot ${index + 1}`;
            const time = document.createElement('small');
            time.textContent = revision.created_at ? new Date(revision.created_at).toLocaleString() : 'Saved locally';
            meta.append(title, time);
            const restore = document.createElement('button');
            restore.type = 'button';
            restore.textContent = 'Restore';
            restore.disabled = !editable;
            restore.addEventListener('click', () => {
                if (!editable || !revision.state) return;
                pushHistory();
                applyState(revision.state);
                pushHistory();
                showNotice('Browser snapshot restored into the editor. Save the template when ready.');
            });
            row.append(meta, restore);
            revisionList.append(row);
        });
    };

    const saveSnapshot = (label = 'Manual snapshot') => {
        if (!editable) return;
        const revisions = loadRevisions();
        revisions.unshift({ label, created_at: new Date().toISOString(), state: captureState() });
        saveRevisions(revisions);
        renderRevisions();
    };

    root.querySelector('[data-email-revision-save]')?.addEventListener('click', () => {
        saveSnapshot('Manual snapshot');
        showNotice('Browser revision snapshot saved locally.');
    });

    root.querySelector('[data-email-revision-clear]')?.addEventListener('click', () => {
        if (!editable) return;
        saveRevisions([]);
        renderRevisions();
        showNotice('Browser revision snapshots cleared.');
    });

    root.querySelector('[data-email-reset-defaults]')?.addEventListener('click', () => {
        if (!editable) return;
        saveSnapshot('Before reset to default');
        pushHistory();
        applyState({
            name: String(defaults.name || nameField?.value || ''),
            description: String(defaults.description || ''),
            subject: String(defaults.subject || ''),
            html: String(defaults.html || ''),
            text: String(defaults.text || ''),
        });
        pushHistory();
        showNotice('Barama default content restored into the editor. Nothing is live until you save and activate it.');
    });

    const appendNamedFields = (payload) => {
        syncRichToHtml();
        form?.querySelectorAll('[name]').forEach((field) => {
            if (!field.name || field.disabled) return;
            if (field.type === 'checkbox' && !field.checked) return;
            payload.set(field.name, field.value);
        });
        payload.set('template_key', templateKey);
        payload.set('locale', templateLocale);
        root.querySelectorAll('[data-email-test-csrf] input[name]').forEach((field) => payload.set(field.name, field.value));
        if (!form || !form.querySelector('[name="subject_template"]')) {
            if (nameField) payload.set('name', nameField.value);
            if (description) payload.set('description', description.value);
            if (subject) payload.set('subject_template', subject.value);
            if (html) payload.set('html_template', html.value);
            if (text) payload.set('text_template', text.value);
        }
    };

    createForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!createMode || !customKeyField) return;
        syncRichToHtml();
        const submitter = event.submitter;
        const key = normalizeCustomKey(customKeyField.value);
        customKeyField.value = key;

        if (!/^custom_[a-z0-9][a-z0-9_]{2,92}$/.test(key)) {
            showNotice('Enter a custom template key with at least 3 characters after custom_. Use lowercase letters, numbers, and underscores only.', 'error');
            customKeyField.focus();
            return;
        }
        if (!String(nameField?.value || '').trim()) {
            showNotice('Enter a template name before creating the custom template.', 'error');
            nameField?.focus();
            return;
        }
        if (!String(html?.value || '').trim()) {
            showNotice('Add email content in the Text Editor before creating the template.', 'error');
            rich?.focus();
            return;
        }

        if (submitter) submitter.disabled = true;
        try {
            const response = await fetch(createForm.action, {
                method: createForm.method || 'POST',
                body: new FormData(createForm),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.ok) {
                throw new Error(result?.message || `Template creation failed with HTTP ${response.status}.`);
            }
            showNotice(result?.data?.message || 'Custom email template created as inactive. Opening the Builder…');
            window.setTimeout(() => window.location.assign(`/dashboard/email-templates/${encodeURIComponent(key)}/builder`), 320);
        } catch (error) {
            showNotice(error instanceof Error ? error.message : 'Custom email template could not be created.', 'error');
            if (submitter) submitter.disabled = false;
        }
    });

    if (form instanceof HTMLFormElement && form !== createForm) {
        form.addEventListener('submit', () => syncRichToHtml(), true);
    }

    root.querySelector('[data-email-send-test]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const recipient = String(testRecipient?.value || '').trim();
        if (!recipient || !testUrl) {
            showNotice('Enter a recipient email address before sending the test.', 'error');
            testRecipient?.focus();
            return;
        }
        const payload = new FormData();
        appendNamedFields(payload);
        payload.set('test_recipient', recipient);
        button.disabled = true;
        if (testStatus) testStatus.textContent = 'Sending test email…';
        try {
            const response = await fetch(testUrl, {
                method: 'POST',
                body: payload,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.ok) {
                throw new Error(result?.message || `Test delivery failed with HTTP ${response.status}.`);
            }
            const message = result?.data?.message || `Test email sent to ${recipient}.`;
            if (testStatus) testStatus.textContent = message;
            showNotice(message);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Test email could not be sent.';
            if (testStatus) testStatus.textContent = message;
            showNotice(message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    syncHtmlToRich();
    history = [captureState()];
    historyIndex = 0;
    renderRevisions();
    refresh();
})();
