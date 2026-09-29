/* BARAMA_PAGE_BUILDER_V116 */
(() => {
    'use strict';

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const iconState = (button, active) => {
        if (!(button instanceof HTMLElement)) return;
        button.classList.toggle('is-active', Boolean(active));
    };

    const init = () => {
        const builder = qs('[data-page-builder]');
        if (!(builder instanceof HTMLElement)) return;

        const form = qs('[data-page-builder-form]', builder);
        const editor = qs('[data-page-editor-surface]', builder);
        const hidden = qs('[data-page-editor-value]', builder);
        const title = qs('[data-page-title]', builder);
        const slug = qs('[data-page-slug]', builder);
        const fullUrl = qs('[data-page-full-url]', builder);
        const copyButton = qs('[data-copy-page-url]', builder);
        const wordCount = qs('[data-page-word-count]', builder);
        const toast = qs('[data-page-builder-toast]', builder);
        const mode = builder.dataset.mode || 'edit';
        const baseUrl = builder.dataset.baseUrl || '';
        let slugTouched = String(slug?.value || '').trim() !== '';

        if (!(form instanceof HTMLFormElement)
            || !(editor instanceof HTMLElement)
            || !(hidden instanceof HTMLTextAreaElement)
            || !(title instanceof HTMLInputElement)
            || !(slug instanceof HTMLInputElement)) {
            return;
        }

        const showToast = (message, error = false) => {
            if (!(toast instanceof HTMLElement)) return;
            toast.textContent = message || (error ? 'The request could not be completed.' : 'Saved.');
            toast.classList.toggle('is-error', error);
            toast.classList.remove('is-visible');
            window.requestAnimationFrame(() => toast.classList.add('is-visible'));
            window.setTimeout(() => toast.classList.remove('is-visible'), 2800);
        };

        const slugify = (value) => String(value || '')
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-')
            .slice(0, 160);

        const updateUrl = () => {
            const value = slugify(slug.value);
            if (slug.value !== value && slug.value.trim() !== '') {
                slug.value = value;
            }

            const url = baseUrl.replace(/\/$/, '') + '/pages/' + value;
            if (fullUrl instanceof HTMLElement) {
                fullUrl.textContent = value ? url : baseUrl.replace(/\/$/, '') + '/pages/…';
                fullUrl.dataset.copyValue = value ? url : '';
            }
        };

        const syncEditor = () => {
            hidden.value = editor.innerHTML.trim();
            const text = (editor.innerText || '').trim();
            const words = text === '' ? 0 : text.split(/\s+/).filter(Boolean).length;
            if (wordCount instanceof HTMLElement) {
                wordCount.textContent = String(words) + (words === 1 ? ' word' : ' words');
            }
        };

        const block = () => {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return null;
            let node = selection.anchorNode;
            if (node && node.nodeType === Node.TEXT_NODE) node = node.parentElement;
            if (!(node instanceof HTMLElement)) return null;
            return node.closest('p,h2,h3,h4,blockquote,li,pre') || node;
        };

        const exec = (command, value = null) => {
            editor.focus();
            document.execCommand(command, false, value);
            syncEditor();
        };

        qsa('[data-editor-command]', builder).forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                const command = button.dataset.editorCommand || '';
                if (!command) return;

                if (command === 'createLink') {
                    const href = window.prompt('Enter link URL');
                    if (href && href.trim()) exec('createLink', href.trim());
                    return;
                }

                if (command === 'insertImage') {
                    const src = window.prompt('Enter image URL');
                    if (src && /^https?:\/\//i.test(src.trim())) exec('insertImage', src.trim());
                    return;
                }

                if (command === 'insertHorizontalRule') {
                    exec('insertHorizontalRule');
                    return;
                }

                exec(command);
            });
        });

        qsa('[data-editor-format]', builder).forEach((select) => {
            select.addEventListener('change', () => {
                const value = select.value || 'p';
                exec('formatBlock', value);
                select.value = '';
            });
        });

        qsa('[data-editor-align]', builder).forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                const target = block();
                if (!(target instanceof HTMLElement)) return;

                const align = button.dataset.editorAlign || 'left';
                if (align === 'left') {
                    target.removeAttribute('data-align');
                    target.style.textAlign = '';
                } else {
                    target.setAttribute('data-align', align);
                    target.style.textAlign = align;
                }

                syncEditor();
            });
        });

        document.addEventListener('selectionchange', () => {
            if (!editor.contains(document.activeElement) && !editor.contains(window.getSelection()?.anchorNode || null)) {
                return;
            }

            qsa('[data-editor-command]', builder).forEach((button) => {
                const command = button.dataset.editorCommand || '';
                if (!['bold','italic','underline','strikeThrough','insertUnorderedList','insertOrderedList','subscript','superscript'].includes(command)) {
                    return;
                }

                try {
                    iconState(button, document.queryCommandState(command));
                } catch (_) {
                    iconState(button, false);
                }
            });
        });

        title.addEventListener('input', () => {
            if (!slugTouched || slug.value.trim() === '') {
                slug.value = slugify(title.value);
                slugTouched = false;
                updateUrl();
            }
        });

        slug.addEventListener('input', () => {
            slugTouched = true;
            updateUrl();
        });

        copyButton?.addEventListener('click', async () => {
            const value = fullUrl?.dataset.copyValue || '';
            if (!value) {
                showToast('Add a page slug before copying the URL.', true);
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
                showToast('Full page URL copied.');
            } catch (_) {
                const temp = document.createElement('textarea');
                temp.value = value;
                temp.style.position = 'fixed';
                temp.style.opacity = '0';
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                temp.remove();
                showToast('Full page URL copied.');
            }
        });

        editor.addEventListener('input', syncEditor);

        qsa('[data-seo-count-source]', builder).forEach((field) => {
            const targetId = field.dataset.seoCountTarget;
            const target = targetId ? qs('#' + CSS.escape(targetId), builder) : null;
            const update = () => {
                if (target instanceof HTMLElement) {
                    target.textContent = String(field.value.length) + ' / ' + String(field.maxLength || '');
                }
            };
            field.addEventListener('input', update);
            update();
        });

        const submitJsonForm = async (targetForm) => {
            const button = qs('[type="submit"]', targetForm);
            const previous = button instanceof HTMLButtonElement ? button.textContent : '';

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
                button.textContent = 'Saving…';
            }

            try {
                const response = await fetch(targetForm.action, {
                    method: targetForm.method || 'POST',
                    body: new FormData(targetForm),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || payload.ok !== true) {
                    throw new Error(payload.message || 'The request could not be completed.');
                }

                return payload.data || {};
            } finally {
                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                    button.textContent = previous;
                }
            }
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            syncEditor();

            try {
                const data = await submitJsonForm(form);
                showToast(mode === 'create' ? 'Page draft created.' : 'Page saved successfully.');

                if (mode === 'create' && data.page_id) {
                    window.setTimeout(() => {
                        window.location.href = baseUrl.replace(/\/$/, '') + '/dashboard/pages/' + encodeURIComponent(String(data.page_id)) + '/edit?created=1';
                    }, 420);
                }
            } catch (error) {
                showToast(error instanceof Error ? error.message : 'The request could not be completed.', true);
            }
        });

        qsa('[data-page-status-form]', builder).forEach((statusForm) => {
            statusForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                try {
                    await submitJsonForm(statusForm);
                    showToast('Page status updated.');
                    window.setTimeout(() => window.location.reload(), 420);
                } catch (error) {
                    showToast(error instanceof Error ? error.message : 'The request could not be completed.', true);
                }
            });
        });

        updateUrl();
        syncEditor();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
