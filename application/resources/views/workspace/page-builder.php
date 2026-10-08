<?php

declare(strict_types=1);

use Barama\Services\PageContentSanitizer;

$pageBuilderMode = ($pageBuilderMode ?? 'edit') === 'create'
    ? 'create'
    : 'edit';

$record = is_array($record ?? null) ? $record : [];
$pageId = (int) ($record['id'] ?? 0);
$pageStatus = strtolower(trim((string) ($record['status'] ?? 'draft')));
$pageTitleValue = (string) ($record['title'] ?? '');
$pageSlugValue = (string) ($record['slug'] ?? '');
$pageContentValue = (string) ($record['content_text'] ?? '');
$pageEditorHtml = PageContentSanitizer::toHtml($pageContentValue);
$pagePublicUrl = $pageSlugValue !== ''
    ? url('/' . rawurlencode($pageSlugValue))
    : '';

$pageIsPublished =
    $pageBuilderMode === 'edit'
    && $pageStatus === 'published';

$pageFormAction = $pageBuilderMode === 'create'
    ? url('/dashboard/pages')
    : url('/dashboard/pages/' . $pageId);

$pageStatusAction = $pageBuilderMode === 'edit'
    ? url('/dashboard/pages/' . $pageId . '/status')
    : '';

$svg = static function (string $name): string {
    $paths = [
        'bold' => '<path d="M7 5h5.5a3.5 3.5 0 0 1 0 7H7zm0 7h6a3.5 3.5 0 0 1 0 7H7z"/>',
        'italic' => '<path d="M10 5h7M7 19h7M14 5 10 19"/>',
        'underline' => '<path d="M7 5v6a5 5 0 0 0 10 0V5M5 21h14"/>',
        'strike' => '<path d="M17 6.5A5 5 0 0 0 13 5c-2.8 0-5 1.3-5 3.4 0 1.2.8 2 2.1 2.6M7 17.5A6.5 6.5 0 0 0 12.5 20c3 0 5.5-1.3 5.5-3.6 0-1.8-1.5-2.7-3.2-3.4M4 12h16"/>',
        'ul' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'ol' => '<path d="M10 6h10M10 12h10M10 18h10M4 5h2v3M4 12h2l-2 3h2M4 18h2l-2 3h2"/>',
        'quote' => '<path d="M7 9h4v4H7v3H4v-4a6 6 0 0 1 6-6M17 9h4v4h-4v3h-3v-4a6 6 0 0 1 6-6"/>',
        'left' => '<path d="M4 6h16M4 10h11M4 14h16M4 18h11"/>',
        'center' => '<path d="M4 6h16M7 10h10M4 14h16M7 18h10"/>',
        'right' => '<path d="M4 6h16M9 10h11M4 14h16M9 18h11"/>',
        'justify' => '<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.1 1.1M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.1-1.1"/>',
        'unlink' => '<path d="M9 15 7 17a5 5 0 0 1-7-7l2-2M15 9l2-2a5 5 0 0 1 7 7l-2 2M3 3l18 18"/>',
        'image' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m21 15-5-5L5 19"/>',
        'hr' => '<path d="M4 12h16"/>',
        'undo' => '<path d="M9 7 4 12l5 5M5 12h8a6 6 0 0 1 6 6"/>',
        'redo' => '<path d="m15 7 5 5-5 5M19 12h-8a6 6 0 0 0-6 6"/>',
        'clear' => '<path d="m7 7 10 10M17 7 7 17"/><path d="M4 21h16"/>',
        'sub' => '<path d="m5 7 8 10M13 7 5 17M16 16h3l-3 4h3"/>',
        'sup' => '<path d="m5 7 8 10M13 7 5 17M16 4h3l-3 4h3"/>',
        'copy' => '<rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
    ];

    return '<svg viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true">'
        . ($paths[$name] ?? $paths['clear'])
        . '</svg>';
};
?>
<link
    rel="stylesheet"
    href="<?= e(asset('css/page-builder-v116.css') . '?v=116') ?>"
>
<script
    src="<?= e(asset('js/page-builder-v120.js') . '?v=120') ?>"
    defer
></script>

<section
    class="page-builder"
    data-page-builder
    data-mode="<?= e($pageBuilderMode) ?>"
    data-base-url="<?= e(rtrim(url('/'), '/')) ?>"
>
    <div
        class="page-builder__notice"
        data-page-builder-toast
        role="status"
        aria-live="polite"
    ></div>

    <header class="page-builder__top">
        <div>
            <span class="eyebrow">Admin Control Center</span>
            <h1>
                <?= e(
                    $pageBuilderMode === 'create'
                        ? 'Create New Page'
                        : 'Edit Page'
                ) ?>
            </h1>
            <p>
                <?= e(
                    $pageBuilderMode === 'create'
                        ? 'Build a new public CMS page with content, publishing and SEO controls.'
                        : 'Edit page content, publishing state and public discovery settings from one workspace.'
                ) ?>
            </p>
        </div>

        <div class="page-builder__top-actions">
            <a
                class="btn btn-outline-secondary"
                href="<?= e(url('/dashboard/pages')) ?>"
            >
                <?= app_icon('menu') ?>
                <span>Page Manager</span>
            </a>

            <?php if ($pageIsPublished && $pagePublicUrl !== ''): ?>
                <a
                    class="btn btn-outline-secondary"
                    href="<?= e($pagePublicUrl) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <?= app_icon('eye') ?>
                    <span>View Page</span>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="page-builder__grid">
        <form
            id="page-builder-form"
            class="page-builder__main"
            action="<?= e($pageFormAction) ?>"
            method="post"
            data-page-builder-form
        >
            <?= csrf_field() ?>

            <label class="page-builder__field">
                <span>Page Name</span>
                <input
                    class="form-control"
                    type="text"
                    name="title"
                    maxlength="191"
                    required
                    value="<?= e($pageTitleValue) ?>"
                    placeholder="Enter page name"
                    data-page-title
                >
            </label>

            <label class="page-builder__field">
                <span>Page slug</span>
                <div class="page-builder__slug">
                    <input
                        class="form-control"
                        type="text"
                        name="slug"
                        maxlength="160"
                        value="<?= e($pageSlugValue) ?>"
                        placeholder="page-slug"
                        data-page-slug
                    >
                    <button
                        class="page-builder__copy"
                        type="button"
                        aria-label="Copy full page URL"
                        title="Copy full page URL"
                        data-copy-page-url
                    >
                        <?= $svg('copy') ?>
                    </button>
                </div>
                <small
                    class="page-builder__full-url"
                    data-page-full-url
                    data-copy-value="<?= e($pagePublicUrl) ?>"
                >
                    <?= e(
                        $pagePublicUrl !== ''
                            ? $pagePublicUrl
                            : rtrim(url('/'), '/') . '/…'
                    ) ?>
                </small>
            </label>

            <div class="page-builder__field">
                <span>Page Content</span>

                <div class="page-builder-editor">
                    <div
                        class="page-builder-toolbar"
                        role="toolbar"
                        aria-label="Text editor toolbar"
                    >
                        <select
                            data-editor-format
                            aria-label="Text style"
                            title="Text style"
                        >
                            <option value="">Style</option>
                            <option value="p">Paragraph</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                            <option value="h4">Heading 4</option>
                            <option value="blockquote">Quote</option>
                            <option value="pre">Code block</option>
                        </select>

                        <span
                            class="page-builder-toolbar__separator"
                            aria-hidden="true"
                        ></span>

                        <button type="button" data-editor-command="bold" title="Bold" aria-label="Bold"><?= $svg('bold') ?></button>
                        <button type="button" data-editor-command="italic" title="Italic" aria-label="Italic"><?= $svg('italic') ?></button>
                        <button type="button" data-editor-command="underline" title="Underline" aria-label="Underline"><?= $svg('underline') ?></button>
                        <button type="button" data-editor-command="strikeThrough" title="Strikethrough" aria-label="Strikethrough"><?= $svg('strike') ?></button>
                        <button type="button" data-editor-command="subscript" title="Subscript" aria-label="Subscript"><?= $svg('sub') ?></button>
                        <button type="button" data-editor-command="superscript" title="Superscript" aria-label="Superscript"><?= $svg('sup') ?></button>

                        <span
                            class="page-builder-toolbar__separator"
                            aria-hidden="true"
                        ></span>

                        <button type="button" data-editor-command="insertUnorderedList" title="Bullet list" aria-label="Bullet list"><?= $svg('ul') ?></button>
                        <button type="button" data-editor-command="insertOrderedList" title="Numbered list" aria-label="Numbered list"><?= $svg('ol') ?></button>
                        <button type="button" data-editor-command="formatBlock" data-editor-value="blockquote" title="Quote" aria-label="Quote"><?= $svg('quote') ?></button>

                        <span
                            class="page-builder-toolbar__separator"
                            aria-hidden="true"
                        ></span>

                        <button type="button" data-editor-align="left" title="Align left" aria-label="Align left"><?= $svg('left') ?></button>
                        <button type="button" data-editor-align="center" title="Align center" aria-label="Align center"><?= $svg('center') ?></button>
                        <button type="button" data-editor-align="right" title="Align right" aria-label="Align right"><?= $svg('right') ?></button>
                        <button type="button" data-editor-align="justify" title="Justify" aria-label="Justify"><?= $svg('justify') ?></button>

                        <span
                            class="page-builder-toolbar__separator"
                            aria-hidden="true"
                        ></span>

                        <button type="button" data-editor-command="createLink" title="Insert link" aria-label="Insert link"><?= $svg('link') ?></button>
                        <button type="button" data-editor-command="unlink" title="Remove link" aria-label="Remove link"><?= $svg('unlink') ?></button>
                        <button type="button" data-editor-command="insertImage" title="Insert image URL" aria-label="Insert image URL"><?= $svg('image') ?></button>
                        <button type="button" data-editor-command="insertHorizontalRule" title="Horizontal line" aria-label="Horizontal line"><?= $svg('hr') ?></button>

                        <span
                            class="page-builder-toolbar__separator"
                            aria-hidden="true"
                        ></span>

                        <button type="button" data-editor-command="undo" title="Undo" aria-label="Undo"><?= $svg('undo') ?></button>
                        <button type="button" data-editor-command="redo" title="Redo" aria-label="Redo"><?= $svg('redo') ?></button>
                        <button type="button" data-editor-command="removeFormat" title="Clear formatting" aria-label="Clear formatting"><?= $svg('clear') ?></button>
                    </div>

                    <div
                        class="page-builder-editor__surface"
                        contenteditable="true"
                        role="textbox"
                        aria-multiline="true"
                        data-page-editor-surface
                        data-placeholder="Start writing your page content…"
                    ><?= $pageEditorHtml ?></div>

                    <textarea
                        name="content_text"
                        data-page-editor-value
                        hidden
                    ><?= e($pageContentValue) ?></textarea>

                    <div class="page-builder-editor__footer">
                        <span>
                            Rich content is sanitized before it is stored
                            and rendered.
                        </span>
                        <strong data-page-word-count>0 words</strong>
                    </div>
                </div>
            </div>
        </form>

        <aside class="page-builder__sidebar">
            <section class="page-builder__sidebar-card">
                <div class="page-builder__status-row">
                    <div>
                        <h2>Publishing</h2>
                        <small>
                            Control save state and public visibility.
                        </small>
                    </div>

                    <span
                        class="status-badge status-badge--<?= e($pageStatus) ?>"
                    >
                        <?= e(ucfirst($pageStatus)) ?>
                    </span>
                </div>

                <div class="page-builder__meta">
                    <div class="page-builder__meta-row">
                        <span>Visibility</span>
                        <strong>
                            <?= e(
                                $pageIsPublished
                                    ? 'Public'
                                    : 'Not public'
                            ) ?>
                        </strong>
                    </div>

                    <div class="page-builder__meta-row">
                        <span>Published</span>
                        <strong>
                            <?= e(
                                trim(
                                    (string) (
                                        $record['published_at'] ?? ''
                                    )
                                ) !== ''
                                    ? (string) $record['published_at']
                                    : '—'
                            ) ?>
                        </strong>
                    </div>

                    <div class="page-builder__meta-row">
                        <span>Updated</span>
                        <strong>
                            <?= e(
                                trim(
                                    (string) (
                                        $record['updated_at'] ?? ''
                                    )
                                ) !== ''
                                    ? (string) $record['updated_at']
                                    : 'New page'
                            ) ?>
                        </strong>
                    </div>
                </div>

                <div class="page-builder__sidebar-actions">
                    <button
                        class="btn btn-primary"
                        type="submit"
                        form="page-builder-form"
                    >
                        <?= app_icon('check') ?>
                        <span>
                            <?= e(
                                $pageBuilderMode === 'create'
                                    ? 'Create Draft Page'
                                    : 'Save Page'
                            ) ?>
                        </span>
                    </button>

                    <?php if ($pageBuilderMode === 'edit'): ?>
                        <?php if ($pageStatus === 'draft'): ?>
                            <form
                                action="<?= e($pageStatusAction) ?>"
                                method="post"
                                data-page-status-form
                            >
                                <?= csrf_field() ?>
                                <input
                                    type="hidden"
                                    name="status"
                                    value="published"
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="submit"
                                >
                                    <?= app_icon('globe') ?>
                                    <span>Publish Page</span>
                                </button>
                            </form>
                        <?php elseif ($pageStatus === 'published'): ?>
                            <form
                                action="<?= e($pageStatusAction) ?>"
                                method="post"
                                data-page-status-form
                            >
                                <?= csrf_field() ?>
                                <input
                                    type="hidden"
                                    name="status"
                                    value="draft"
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="submit"
                                >
                                    <?= app_icon('edit') ?>
                                    <span>Move to Draft</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (in_array($pageStatus, ['draft', 'published'], true)): ?>
                            <form
                                action="<?= e($pageStatusAction) ?>"
                                method="post"
                                data-page-status-form
                            >
                                <?= csrf_field() ?>
                                <input
                                    type="hidden"
                                    name="status"
                                    value="archived"
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="submit"
                                >
                                    <?= app_icon('layers') ?>
                                    <span>Archive Page</span>
                                </button>
                            </form>
                        <?php elseif ($pageStatus === 'archived'): ?>
                            <form
                                action="<?= e($pageStatusAction) ?>"
                                method="post"
                                data-page-status-form
                            >
                                <?= csrf_field() ?>
                                <input
                                    type="hidden"
                                    name="status"
                                    value="draft"
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="submit"
                                >
                                    <?= app_icon('edit') ?>
                                    <span>Restore Draft</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="page-builder__sidebar-card">
                <h2>Page Summary</h2>

                <label
                    class="page-builder__field page-builder__field--compact"
                >
                    <span>Excerpt</span>
                    <textarea
                        class="form-control"
                        name="excerpt"
                        rows="4"
                        maxlength="500"
                        form="page-builder-form"
                        placeholder="Optional short introduction"
                    ><?= e((string) ($record['excerpt'] ?? '')) ?></textarea>
                </label>
            </section>

            <section class="page-builder__sidebar-card">
                <h2>SEO &amp; Discovery</h2>

                <label
                    class="page-builder__field page-builder__field--compact"
                >
                    <span>SEO title</span>
                    <input
                        class="form-control"
                        name="seo_title"
                        maxlength="191"
                        form="page-builder-form"
                        value="<?= e((string) ($record['seo_title'] ?? '')) ?>"
                        placeholder="Defaults to page name"
                        data-seo-count-source
                        data-seo-count-target="page-seo-title-count"
                    >
                    <small
                        id="page-seo-title-count"
                        class="page-builder__seo-count"
                    ></small>
                </label>

                <label
                    class="page-builder__field page-builder__field--compact"
                >
                    <span>SEO description</span>
                    <textarea
                        class="form-control"
                        name="seo_description"
                        rows="4"
                        maxlength="300"
                        form="page-builder-form"
                        placeholder="Defaults to excerpt or page content"
                        data-seo-count-source
                        data-seo-count-target="page-seo-description-count"
                    ><?= e((string) ($record['seo_description'] ?? '')) ?></textarea>
                    <small
                        id="page-seo-description-count"
                        class="page-builder__seo-count"
                    ></small>
                </label>

                <label
                    class="page-builder__field page-builder__field--compact"
                >
                    <span>Robots directive</span>
                    <select
                        class="form-select"
                        name="robots_directive"
                        form="page-builder-form"
                    >
                        <?php foreach (
                            [
                                'index,follow',
                                'noindex,follow',
                                'noindex,nofollow'
                            ] as $robotsOption
                        ): ?>
                            <option
                                value="<?= e($robotsOption) ?>"
                                <?= (string) (
                                    $record['robots_directive']
                                    ?? 'index,follow'
                                ) === $robotsOption
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($robotsOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </section>
        </aside>
    </div>
</section>
