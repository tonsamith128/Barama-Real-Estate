/* BARAMA_ADMIN_EMAIL_TEMPLATE_BUILDER_V139 */
(() => {
    const root = document.querySelector('[data-email-template-builder][data-email-template-version="139"]');
    if (!root) return;

    const form = root.querySelector('[data-email-builder-form]');
    const nameField = root.querySelector('[data-email-name]');
    const description = root.querySelector('[data-email-description]');
    const subject = root.querySelector('[data-email-subject]');
    const html = root.querySelector('[data-email-html]');
    const text = root.querySelector('[data-email-text]');
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
    const templateKey = root.getAttribute('data-email-template-key') || 'unknown';
    const testUrl = root.getAttribute('data-email-test-url') || '';
    const editable = ['fallback', 'inactive'].includes(root.getAttribute('data-email-template-status') || '');
    const BLOCK_START = '<!--barama:block:';
    const BLOCK_END = '<!--/barama:block-->';
    const revisionStorageKey = `barama.email-builder.revisions.${templateKey}`;
    let samples = {};
    let defaults = {};
    let lastField = html || subject || text;
    let history = [];
    let historyIndex = -1;
    let historyTimer = null;
    let applyingHistory = false;

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

    const replaceVariables = (value) => String(value || '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key) => {
        const normalized = String(key || '').toLowerCase();
        return Object.prototype.hasOwnProperty.call(samples, normalized) ? String(samples[normalized]) : '';
    });

    const refresh = () => {
        if (previewSubject) previewSubject.textContent = replaceVariables(subject?.value || '');
        if (previewHtml) previewHtml.srcdoc = replaceVariables(html?.value || '');
        if (previewText) previewText.textContent = replaceVariables(text?.value || '');
        if (htmlCount) htmlCount.textContent = `${(html?.value || '').length.toLocaleString()} / 20,000`;
        if (textCount) textCount.textContent = `${(text?.value || '').length.toLocaleString()} / 10,000`;
    };

    const captureState = () => ({
        name: nameField?.value || '',
        description: description?.value || '',
        subject: subject?.value || '',
        html: html?.value || '',
        text: text?.value || '',
    });

    const stateSignature = (state) => JSON.stringify(state);

    const applyState = (state) => {
        applyingHistory = true;
        if (nameField) nameField.value = String(state?.name ?? '');
        if (description) description.value = String(state?.description ?? '');
        if (subject) subject.value = String(state?.subject ?? '');
        if (html) html.value = String(state?.html ?? '');
        if (text) text.value = String(state?.text ?? '');
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

    [nameField, description, subject, html, text].forEach((field) => {
        field?.addEventListener('focus', () => { lastField = field; });
        field?.addEventListener('input', () => {
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

    const insertAtCursor = (field, value) => {
        if (!field || field.readOnly || field.disabled) return;
        const start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
        const end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
        field.setRangeText(value, start, end, 'end');
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const wrapSelection = (field, before, after) => {
        if (!field || field.readOnly || field.disabled) return;
        const start = field.selectionStart || 0;
        const end = field.selectionEnd || start;
        const selected = field.value.slice(start, end) || 'Text';
        field.setRangeText(`${before}${selected}${after}`, start, end, 'select');
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
    };

    root.querySelectorAll('[data-email-variable]').forEach((button) => {
        button.addEventListener('click', () => insertAtCursor(lastField, button.getAttribute('data-email-variable') || ''));
    });

    root.querySelectorAll('[data-email-insert-html]').forEach((button) => {
        button.addEventListener('click', () => insertAtCursor(html, button.getAttribute('data-email-insert-html') || ''));
    });

    root.querySelectorAll('[data-email-insert-block]').forEach((button) => {
        button.addEventListener('click', () => {
            const type = button.getAttribute('data-email-insert-block') || 'block';
            const content = button.getAttribute('data-email-block-html') || '';
            const block = `\n${BLOCK_START}${type}-->\n${content}\n${BLOCK_END}\n`;
            insertAtCursor(html, block);
        });
    });

    root.querySelectorAll('[data-email-wrap]').forEach((button) => {
        button.addEventListener('click', () => {
            const tag = button.getAttribute('data-email-wrap') || 'strong';
            wrapSelection(html, `<${tag}>`, `</${tag}>`);
        });
    });

    root.querySelectorAll('[data-email-align]').forEach((button) => {
        button.addEventListener('click', () => {
            const alignment = button.getAttribute('data-email-align') || 'left';
            wrapSelection(html, `<div style="text-align:${alignment};">`, '</div>');
        });
    });

    root.querySelector('[data-email-apply-text-color]')?.addEventListener('click', () => {
        const color = root.querySelector('[data-email-text-color]')?.value || '#1f2937';
        wrapSelection(html, `<span style="color:${color};">`, '</span>');
    });

    root.querySelector('[data-email-apply-background]')?.addEventListener('click', () => {
        const color = root.querySelector('[data-email-background-color]')?.value || '#f8fafc';
        wrapSelection(html, `<div style="background:${color};padding:14px 16px;border-radius:8px;">`, '</div>');
    });

    root.querySelector('[data-email-apply-font-size]')?.addEventListener('click', () => {
        const size = Number(root.querySelector('[data-email-font-size]')?.value || 16);
        const safeSize = [12, 14, 16, 18, 20, 24, 28].includes(size) ? size : 16;
        wrapSelection(html, `<span style="font-size:${safeSize}px;line-height:1.55;">`, '</span>');
    });

    root.querySelectorAll('[data-email-history]').forEach((button) => {
        button.addEventListener('click', () => {
            button.getAttribute('data-email-history') === 'redo' ? redo() : undo();
        });
    });

    const currentBlock = () => {
        if (!html) return null;
        const value = html.value;
        const pos = html.selectionStart || 0;
        const start = value.lastIndexOf(BLOCK_START, pos);
        if (start < 0) return null;
        const openEnd = value.indexOf('-->', start);
        if (openEnd < 0 || openEnd > pos) return null;
        const close = value.indexOf(BLOCK_END, openEnd + 3);
        if (close < 0 || pos > close + BLOCK_END.length) return null;
        const interveningClose = value.lastIndexOf(BLOCK_END, pos);
        if (interveningClose > start) return null;
        return { start, end: close + BLOCK_END.length };
    };

    const setHtmlSelection = (value, start, end) => {
        if (!html) return;
        html.value = value;
        html.focus();
        html.setSelectionRange(start, end);
        html.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const blockAction = (action) => {
        if (!html || html.readOnly || html.disabled) return;
        const block = currentBlock();
        if (!block) {
            showNotice('Place the cursor inside a block inserted by the Builder first.', 'error');
            return;
        }
        const value = html.value;
        const current = value.slice(block.start, block.end);
        if (action === 'duplicate') {
            const next = `${value.slice(0, block.end)}\n${current}${value.slice(block.end)}`;
            setHtmlSelection(next, block.end + 1, block.end + 1 + current.length);
            return;
        }
        if (action === 'delete') {
            const next = value.slice(0, block.start) + value.slice(block.end);
            setHtmlSelection(next, block.start, block.start);
            return;
        }
        if (action === 'up') {
            const before = value.slice(0, block.start);
            const previousStart = before.lastIndexOf(BLOCK_START);
            if (previousStart < 0) return showNotice('This Builder block is already first.', 'error');
            const previousClose = value.indexOf(BLOCK_END, previousStart);
            if (previousClose < 0 || previousClose + BLOCK_END.length > block.start) return showNotice('The previous HTML is not a movable Builder block.', 'error');
            const previousEnd = previousClose + BLOCK_END.length;
            const previous = value.slice(previousStart, previousEnd);
            const between = value.slice(previousEnd, block.start);
            const next = value.slice(0, previousStart) + current + between + previous + value.slice(block.end);
            setHtmlSelection(next, previousStart, previousStart + current.length);
            return;
        }
        if (action === 'down') {
            const nextStart = value.indexOf(BLOCK_START, block.end);
            if (nextStart < 0) return showNotice('This Builder block is already last.', 'error');
            const nextClose = value.indexOf(BLOCK_END, nextStart);
            if (nextClose < 0) return showNotice('The next HTML is not a movable Builder block.', 'error');
            const nextEnd = nextClose + BLOCK_END.length;
            const nextBlock = value.slice(nextStart, nextEnd);
            const between = value.slice(block.end, nextStart);
            const updated = value.slice(0, block.start) + nextBlock + between + current + value.slice(nextEnd);
            const newStart = block.start + nextBlock.length + between.length;
            setHtmlSelection(updated, newStart, newStart + current.length);
        }
    };

    root.querySelectorAll('[data-email-block-action]').forEach((button) => {
        button.addEventListener('click', () => blockAction(button.getAttribute('data-email-block-action') || ''));
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
        revisions.unshift({
            label,
            created_at: new Date().toISOString(),
            state: captureState(),
        });
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
        const next = {
            name: String(defaults.name || nameField?.value || ''),
            description: String(defaults.description || ''),
            subject: String(defaults.subject || ''),
            html: String(defaults.html || ''),
            text: String(defaults.text || ''),
        };
        applyState(next);
        pushHistory();
        showNotice('Barama default content restored into the editor. Nothing is live until you save and activate it.');
    });

    const appendNamedFields = (payload) => {
        form?.querySelectorAll('[name]').forEach((field) => {
            if (!field.name || field.disabled) return;
            if (field.type === 'checkbox' && !field.checked) return;
            payload.set(field.name, field.value);
        });
        payload.set('template_key', templateKey);
        root.querySelectorAll('[data-email-test-csrf] input[name]').forEach((field) => {
            payload.set(field.name, field.value);
        });
        if (!form || !form.querySelector('[name="subject_template"]')) {
            if (nameField) payload.set('name', nameField.value);
            if (description) payload.set('description', description.value);
            if (subject) payload.set('subject_template', subject.value);
            if (html) payload.set('html_template', html.value);
            if (text) payload.set('text_template', text.value);
        }
    };

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
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            let result = null;
            try { result = await response.json(); } catch (_) { result = null; }
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

    // Preserve the focused editor target when clicking toolbar controls.
    form?.querySelectorAll('input, textarea').forEach((field) => {
        field.addEventListener('focus', () => { lastField = field; });
    });

    history = [captureState()];
    historyIndex = 0;
    renderRevisions();
    refresh();
})();
