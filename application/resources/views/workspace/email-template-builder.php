<?php

declare(strict_types=1);

$createMode = !empty($emailTemplateCreateMode);
$key = $createMode ? 'custom_new' : (string) ($emailTemplateKey ?? '');
$definition = is_array($emailTemplateDefinition ?? null) ? $emailTemplateDefinition : [];
$template = is_array($emailTemplate ?? null) ? $emailTemplate : null;
$defaults = is_array($emailTemplateDefaults ?? null) ? $emailTemplateDefaults : [];
$variables = is_array($emailTemplateVariables ?? null) ? array_values($emailTemplateVariables) : [];
$samples = is_array($emailTemplateSamples ?? null) ? $emailTemplateSamples : [];
$locale = $createMode ? 'en' : strtolower((string) ($emailTemplateLocale ?? 'en'));
$locales = is_array($emailTemplateLocales ?? null) ? $emailTemplateLocales : ['en' => 'English', 'km' => 'ខ្មែរ', 'zh' => '中文'];
$variant = is_array($emailTemplateVariant ?? null) ? $emailTemplateVariant : null;
$serverRevisions = is_array($emailTemplateRevisions ?? null) ? $emailTemplateRevisions : [];
$isBase = $locale === 'en';
$record = $isBase ? $template : $variant;
$label = (string) ($definition['label'] ?? ($createMode ? 'Create New' : $key));
$group = (string) ($definition['group'] ?? ($createMode ? 'Custom' : 'Email'));
$description = (string) ($definition['description'] ?? ($createMode ? 'Create a reusable custom email template with the full Barama Email Template Builder.' : ''));
$sensitive = !empty($definition['security_sensitive']);
if ($createMode) {
    $status = 'new';
    $editable = true;
    $name = (string) ($defaults['name'] ?? '');
    $internalDescription = (string) ($defaults['description'] ?? '');
    $subject = (string) ($defaults['subject'] ?? '{{app_name}} — your update');
    $html = (string) ($defaults['html'] ?? '<p>Hello {{recipient_name}},</p><p>{{message}}</p>');
    $text = (string) ($defaults['text'] ?? "Hello {{recipient_name}},\n\n{{message}}");
} elseif ($isBase) {
    $status = is_array($template) ? (string) ($template['status'] ?? 'inactive') : 'fallback';
    $editable = in_array($status, ['fallback', 'inactive'], true);
    $name = is_array($template) ? (string) ($template['name'] ?? '') : (string) ($defaults['name'] ?? ($label . ' email'));
    $internalDescription = is_array($template) ? (string) ($template['description'] ?? '') : (string) ($defaults['description'] ?? '');
    $subject = is_array($template) ? (string) ($template['subject_template'] ?? '') : (string) ($defaults['subject'] ?? '{{app_name}} notification');
    $html = is_array($template) ? (string) ($template['html_template'] ?? '') : (string) ($defaults['html'] ?? '<p>{{message}}</p>');
    $text = is_array($template) ? (string) ($template['text_template'] ?? '') : (string) ($defaults['text'] ?? '{{message}}');
} else {
    $status = $template === null ? 'base-required' : (is_array($variant) ? (string) ($variant['status'] ?? 'inactive') : 'fallback');
    $editable = $template !== null && in_array($status, ['fallback', 'inactive'], true);
    $name = is_array($template) ? (string) ($template['name'] ?? ($label . ' email')) : (string) ($defaults['name'] ?? ($label . ' email'));
    $internalDescription = is_array($template) ? (string) ($template['description'] ?? '') : (string) ($defaults['description'] ?? '');
    $subject = is_array($variant) ? (string) ($variant['subject_template'] ?? '') : (is_array($template) ? (string) ($template['subject_template'] ?? '') : (string) ($defaults['subject'] ?? ''));
    $html = is_array($variant) ? (string) ($variant['html_template'] ?? '') : (is_array($template) ? (string) ($template['html_template'] ?? '') : (string) ($defaults['html'] ?? ''));
    $text = is_array($variant) ? (string) ($variant['text_template'] ?? '') : (is_array($template) ? (string) ($template['text_template'] ?? '') : (string) ($defaults['text'] ?? ''));
}
$formId = 'email-template-builder-form';
$canGreeting = in_array('recipient_name', $variables, true);
$canAction = in_array('action_url', $variables, true);
$testUrl = $createMode ? '' : url('/dashboard/email-templates/' . rawurlencode($key) . '/test');
$variantUrl = $createMode ? '' : url('/dashboard/email-templates/' . rawurlencode($key) . '/variants/' . rawurlencode($locale));
$statusUrl = !$createMode && $isBase && is_array($template)
    ? url('/dashboard/email-templates/' . (int) $template['id'] . '/status')
    : ($variantUrl !== '' ? $variantUrl . '/status' : '');
$editorIcon = static function (string $name): string {
    $path = match ($name) {
        'undo' => '<path d="M9 7 4 12l5 5"/><path d="M5 12h7a7 7 0 0 1 7 7"/>',
        'redo' => '<path d="m15 7 5 5-5 5"/><path d="M19 12h-7a7 7 0 0 0-7 7"/>',
        'bold' => '<path d="M7 4h6a4 4 0 0 1 0 8H7z"/><path d="M7 12h7a4 4 0 0 1 0 8H7z"/>',
        'italic' => '<path d="M10 4h8M6 20h8M14 4l-4 16"/>',
        'underline' => '<path d="M6 4v7a6 6 0 0 0 12 0V4M5 21h14"/>',
        'paragraph' => '<path d="M7 5h10M7 9h10M7 13h7M7 17h8"/>',
        'h2' => '<path d="M4 5v14M11 5v14M4 12h7"/><path d="M15 10a3 3 0 1 1 5 2l-5 6h6"/>',
        'h3' => '<path d="M4 5v14M11 5v14M4 12h7"/><path d="M16 10h4l-3 3a3 3 0 1 1-1 5"/>',
        'left' => '<path d="M4 6h16M4 10h11M4 14h16M4 18h9"/>',
        'center' => '<path d="M4 6h16M7 10h10M4 14h16M8 18h8"/>',
        'right' => '<path d="M4 6h16M9 10h11M4 14h16M11 18h9"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"/><path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"/>',
        'text-color' => '<path d="M6 19 12 5l6 14M8 15h8"/><path d="M5 22h14"/>',
        'highlight' => '<path d="m9 11 4-4 4 4-4 4z"/><path d="m5 19 4-4 4 4-4 3H5z"/>',
        'text' => '<path d="M5 6h14M12 6v12M8 18h8"/>',
        'photo' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="2"/><path d="m21 15-5-5L5 20"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/>',
        'youtube' => '<rect x="3" y="6" width="18" height="12" rx="4"/><path d="m10 9 5 3-5 3z"/>',
        'media' => '<path d="M4 7h6l2 2h8v10H4z"/><path d="M8 13h8M8 16h5"/>',
        'code' => '<path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"/>',
        'up' => '<path d="m12 19 0-14M6 11l6-6 6 6"/>',
        'down' => '<path d="m12 5 0 14M6 13l6 6 6-6"/>',
        'copy' => '<rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        default => '<circle cx="12" cy="12" r="8"/>',
    };

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};
?>
<link rel="stylesheet" href="<?= e(asset('css/admin-email-template-builder-v143.css') . '?v=143') ?>">

<section
    class="workspace-page email-builder"
    data-email-template-builder
    data-email-template-status="<?= e($status) ?>"
    data-email-template-key="<?= e($key) ?>"
    data-email-template-version="143"
    data-email-template-locale="<?= e($locale) ?>"
    data-email-create-mode="<?= $createMode ? '1' : '0' ?>"
    data-email-test-url="<?= e($testUrl) ?>"
    data-email-media-library-url="<?= e(url('/dashboard/email-templates/media-library')) ?>"
>
    <header class="email-builder__hero">
        <div class="email-builder__hero-main">
            <a class="email-builder__back" href="<?= e(url('/dashboard/email-templates')) ?>"><?= app_icon('layers') ?> Email Templates</a>
            <div class="email-builder__title-row">
                <span class="email-builder__title-icon"><?= app_icon($sensitive ? 'shield' : 'mail') ?></span>
                <div>
                    <span class="eyebrow">Email Template Builder · <?= e($group) ?></span>
                    <h1><?= e($label) ?></h1>
                    <p><?= e($description) ?></p>
                </div>
            </div>
        </div>
        <div class="email-builder__hero-state">
            <small>Delivery state</small>
            <strong><?= e($status === 'fallback' ? 'System fallback' : ucfirst($status)) ?></strong>
            <span><?= e($createMode ? 'Not saved yet · will start inactive' : ($isBase ? (str_starts_with($key, 'custom_') ? 'Standalone custom template' : (is_array($template) ? 'Custom base override exists' : 'Barama default is live')) : (is_array($variant) ? 'Translated variant exists' : 'Falls back to English'))) ?></span>
        </div>
    </header>

    <?php if (!$createMode): ?>
    <nav class="email-builder__locale-tabs" aria-label="Email language variants">
        <?php foreach ($locales as $localeKey => $localeLabel): ?>
            <a class="<?= $locale === (string) $localeKey ? 'is-active' : '' ?>" href="<?= e(url('/dashboard/email-templates/' . rawurlencode($key) . '/builder?locale=' . rawurlencode((string) $localeKey))) ?>">
                <strong><?= e((string) $localeLabel) ?></strong>
                <small><?= e((string) $localeKey === 'en' ? 'Base' : 'Variant') ?></small>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php else: ?>
        <div class="email-builder__state-note email-builder__state-note--create"><div><strong>Start with the English base template.</strong><p>After creation, reopen this template to add Khmer and Chinese variants, revisions, test delivery and lifecycle controls.</p></div></div>
    <?php endif; ?>

    <?php if (!$isBase && $template === null): ?>
        <div class="email-builder__state-note"><div><strong>Base override required.</strong><p>Create the English/base override first. Translated variants always fall back safely to the active base email.</p></div></div>
    <?php elseif (!$isBase && $variant === null): ?>
        <div class="email-builder__state-note"><div><strong><?= e((string) ($locales[$locale] ?? strtoupper($locale))) ?> currently uses the English fallback.</strong><p>Create an inactive translation, review it, then activate it when ready.</p></div></div>
    <?php endif; ?>

    <div class="email-builder__notice" data-workspace-notice hidden></div>

    <?php if ($sensitive): ?>
        <div class="email-builder__security-note">
            <?= app_icon('shield') ?>
            <div><strong>Security-sensitive email</strong><p>This template has a restricted variable set. Test sends use sample values only; real token URLs are never exposed by the Builder.</p></div>
        </div>
    <?php endif; ?>

    <?php if ($status === 'active'): ?>
        <div class="email-builder__state-note email-builder__state-note--active">
            <?= app_icon('check') ?>
            <div><strong>This override is currently live.</strong><p>Deactivate it before changing content. Preview and test delivery remain available while the template is read-only.</p></div>
        </div>
    <?php elseif ($status === 'archived'): ?>
        <div class="email-builder__state-note">
            <?= app_icon('layers') ?>
            <div><strong>This override is archived.</strong><p>Archived templates are retained for audit context and remain read-only.</p></div>
        </div>
    <?php endif; ?>

    <div class="email-builder__workspace">
        <aside class="email-builder__elements" aria-label="Email elements">
            <div class="email-builder__aside-heading">
                <span>Elements</span>
                <small>Reusable blocks</small>
            </div>
            <div class="email-builder__element-list">
                <?php if ($canGreeting): ?>
                    <button type="button" data-email-insert-block="greeting" data-email-block-html="&lt;p style=&quot;margin:0 0 16px;&quot;&gt;Hello {{recipient_name}},&lt;/p&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('user') ?><span><strong>Greeting</strong><small>Recipient greeting</small></span></button>
                <?php endif; ?>
                <button type="button" data-email-insert-block="heading" data-email-block-html="&lt;h2 style=&quot;margin:0 0 14px;font-size:24px;line-height:1.3;&quot;&gt;Heading&lt;/h2&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('edit') ?><span><strong>Heading</strong><small>Section title</small></span></button>
                <button type="button" data-email-insert-block="paragraph" data-email-block-html="&lt;p style=&quot;margin:0 0 16px;line-height:1.65;&quot;&gt;Write your message here.&lt;/p&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('message') ?><span><strong>Paragraph</strong><small>Body copy</small></span></button>
                <?php if ($canAction): ?>
                    <button type="button" data-email-insert-block="cta" data-email-block-html="&lt;p style=&quot;margin:20px 0;&quot;&gt;&lt;a href=&quot;{{action_url}}&quot; style=&quot;display:inline-block;padding:12px 20px;border-radius:8px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;&quot;&gt;Open in {{app_name}}&lt;/a&gt;&lt;/p&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('globe') ?><span><strong>CTA button</strong><small>Primary action</small></span></button>
                <?php endif; ?>
                <button type="button" data-email-insert-block="image" data-email-block-html="&lt;div style=&quot;margin:18px 0;text-align:center;&quot;&gt;&lt;img src=&quot;{{app_url}}/assets/images/branding/barama-logo-header.png&quot; alt=&quot;{{app_name}}&quot; style=&quot;display:inline-block;max-width:180px;width:100%;height:auto;border:0;&quot;&gt;&lt;/div&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('layers') ?><span><strong>Photo / image</strong><small>Email-safe image</small></span></button>
                <button type="button" data-email-insert-block="columns" data-email-block-html="&lt;table role=&quot;presentation&quot; width=&quot;100%&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot; style=&quot;margin:18px 0;border-collapse:collapse;&quot;&gt;&lt;tr&gt;&lt;td width=&quot;50%&quot; valign=&quot;top&quot; style=&quot;padding:0 10px 0 0;line-height:1.6;&quot;&gt;&lt;strong&gt;Column one&lt;/strong&gt;&lt;br&gt;Add content here.&lt;/td&gt;&lt;td width=&quot;50%&quot; valign=&quot;top&quot; style=&quot;padding:0 0 0 10px;line-height:1.6;&quot;&gt;&lt;strong&gt;Column two&lt;/strong&gt;&lt;br&gt;Add content here.&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('layers') ?><span><strong>Two columns</strong><small>Responsive table layout</small></span></button>
                <button type="button" data-email-insert-block="highlight" data-email-block-html="&lt;div style=&quot;margin:18px 0;padding:16px 18px;border:1px solid #dbe4f0;border-radius:10px;background:#f8fafc;line-height:1.6;&quot;&gt;&lt;strong&gt;Important information&lt;/strong&gt;&lt;br&gt;Add a short highlighted message here.&lt;/div&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('shield') ?><span><strong>Highlight box</strong><small>Callout content</small></span></button>
                <button type="button" data-email-insert-block="divider" data-email-block-html="&lt;hr style=&quot;margin:22px 0;border:0;border-top:1px solid #e2e8f0;&quot;&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('layers') ?><span><strong>Divider</strong><small>Visual separator</small></span></button>
                <button type="button" data-email-insert-block="spacer" data-email-block-html="&lt;div style=&quot;height:24px;line-height:24px;font-size:1px;&quot;&gt;&amp;nbsp;&lt;/div&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('layers') ?><span><strong>Spacer</strong><small>Vertical space</small></span></button>
                <button type="button" data-email-insert-block="footer-note" data-email-block-html="&lt;p style=&quot;margin:22px 0 0;color:#64748b;font-size:12px;line-height:1.55;&quot;&gt;This message was sent by {{app_name}}.&lt;/p&gt;" <?= $editable ? '' : 'disabled' ?>><?= app_icon('mail') ?><span><strong>Footer note</strong><small>Small-print text</small></span></button>
            </div>

            <div class="email-builder__aside-heading email-builder__aside-heading--variables">
                <span>Variables</span>
                <small><?= e((string) count($variables)) ?> available</small>
            </div>
            <div class="email-builder__variable-list">
                <?php foreach ($variables as $variable): ?>
                    <button type="button" data-email-variable="{{<?= e((string) $variable) ?>}}" <?= $editable ? '' : 'disabled' ?>>{{<?= e((string) $variable) ?>}}</button>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="email-builder__editor">
            <?php if ($createMode): ?>
                <form id="<?= e($formId) ?>" action="<?= e(url('/dashboard/email-templates')) ?>" method="post" data-email-builder-form data-email-create-form>
                    <?= csrf_field() ?><input type="hidden" name="locale" value="en">
            <?php elseif ($isBase && $status === 'fallback'): ?>
                <form id="<?= e($formId) ?>" action="<?= e(url('/dashboard/email-templates')) ?>" method="post" data-workspace-form data-email-builder-form>
                    <?= csrf_field() ?><input type="hidden" name="template_key" value="<?= e($key) ?>"><input type="hidden" name="locale" value="en">
            <?php elseif ($isBase && $status === 'inactive'): ?>
                <form id="<?= e($formId) ?>" action="<?= e(url('/dashboard/email-templates/' . (int) $template['id'])) ?>" method="post" data-workspace-form data-email-builder-form>
                    <?= csrf_field() ?><input type="hidden" name="template_key" value="<?= e($key) ?>"><input type="hidden" name="locale" value="en">
            <?php elseif (!$isBase && in_array($status, ['fallback', 'inactive'], true)): ?>
                <form id="<?= e($formId) ?>" action="<?= e($variantUrl) ?>" method="post" data-workspace-form data-email-builder-form>
                    <?= csrf_field() ?><input type="hidden" name="template_key" value="<?= e($key) ?>"><input type="hidden" name="locale" value="<?= e($locale) ?>">
            <?php else: ?>
                <div id="<?= e($formId) ?>" data-email-builder-form>
            <?php endif; ?>

                <nav class="email-builder__tabs" aria-label="Builder sections">
                    <button class="is-active" type="button" data-email-builder-tab="content">Content</button>
                    <button type="button" data-email-builder-tab="plain">Plain text</button>
                    <button type="button" data-email-builder-tab="settings">Settings & tools</button>
                </nav>

                <section class="email-builder__panel" data-email-builder-panel="content">
                    <div class="email-builder__field-grid">
                        <label>
                            <span>Template name</span>
                            <input type="text" name="name" maxlength="160" value="<?= e($name) ?>" <?= ($editable && $isBase) ? 'required' : 'readonly' ?> data-email-name>
                        </label>
                        <?php if ($createMode): ?>
                            <label>
                                <span>Template key</span>
                                <input type="text" name="template_key" maxlength="100" value="custom_" pattern="custom_[a-z0-9][a-z0-9_]{2,92}" required data-email-custom-key autocomplete="off" spellcheck="false">
                                <small class="email-builder__field-help">Stable identifier. Must start with <code>custom_</code>; lowercase letters, numbers and underscores only.</small>
                            </label>
                        <?php endif; ?>
                        <label>
                            <span>Internal description</span>
                            <input type="text" name="description" maxlength="2000" value="<?= e($internalDescription) ?>" <?= ($editable && $isBase) ? '' : 'readonly' ?> data-email-description>
                        </label>
                        <label class="email-builder__field-grid--wide">
                            <span>Subject line</span>
                            <input type="text" name="subject_template" maxlength="255" value="<?= e($subject) ?>" <?= $editable ? 'required' : 'readonly' ?> data-email-subject>
                        </label>
                    </div>

                    <div class="email-builder__code-card email-builder__text-editor-card">
                        <header>
                            <div><strong>Text Editor</strong><small>Design the final email visually. HTML source remains available for advanced editing.</small></div>
                            <span>20,000 characters max</span>
                        </header>
                        <div class="email-builder__quick-toolbar" role="toolbar" aria-label="Text Editor toolbar">
                            <div class="email-builder__toolbar-group" aria-label="History">
                                <button type="button" data-email-history="undo" <?= $editable ? '' : 'disabled' ?> title="Undo" aria-label="Undo"><?= $editorIcon('undo') ?></button>
                                <button type="button" data-email-history="redo" <?= $editable ? '' : 'disabled' ?> title="Redo" aria-label="Redo"><?= $editorIcon('redo') ?></button>
                            </div>
                            <div class="email-builder__toolbar-group" aria-label="Formatting">
                                <button type="button" data-email-command="bold" <?= $editable ? '' : 'disabled' ?> title="Bold" aria-label="Bold"><?= $editorIcon('bold') ?></button>
                                <button type="button" data-email-command="italic" <?= $editable ? '' : 'disabled' ?> title="Italic" aria-label="Italic"><?= $editorIcon('italic') ?></button>
                                <button type="button" data-email-command="underline" <?= $editable ? '' : 'disabled' ?> title="Underline" aria-label="Underline"><?= $editorIcon('underline') ?></button>
                                <button type="button" data-email-format="p" <?= $editable ? '' : 'disabled' ?> title="Paragraph" aria-label="Paragraph"><?= $editorIcon('paragraph') ?></button>
                                <button type="button" data-email-format="h2" <?= $editable ? '' : 'disabled' ?> title="Heading 2" aria-label="Heading 2"><?= $editorIcon('h2') ?></button>
                                <button type="button" data-email-format="h3" <?= $editable ? '' : 'disabled' ?> title="Heading 3" aria-label="Heading 3"><?= $editorIcon('h3') ?></button>
                            </div>
                            <div class="email-builder__toolbar-group" aria-label="Alignment">
                                <button type="button" data-email-align="left" <?= $editable ? '' : 'disabled' ?> title="Align left" aria-label="Align left"><?= $editorIcon('left') ?></button>
                                <button type="button" data-email-align="center" <?= $editable ? '' : 'disabled' ?> title="Align center" aria-label="Align center"><?= $editorIcon('center') ?></button>
                                <button type="button" data-email-align="right" <?= $editable ? '' : 'disabled' ?> title="Align right" aria-label="Align right"><?= $editorIcon('right') ?></button>
                            </div>
                            <div class="email-builder__toolbar-group email-builder__toolbar-style" aria-label="Typography">
                                <label class="email-builder__toolbar-color" title="Text color" aria-label="Text color">
                                    <?= $editorIcon('text-color') ?>
                                    <input type="color" value="#1f2937" data-email-text-color <?= $editable ? '' : 'disabled' ?> aria-label="Choose text color">
                                </label>
                                <label class="email-builder__toolbar-color" title="Highlight color" aria-label="Highlight color">
                                    <?= $editorIcon('highlight') ?>
                                    <input type="color" value="#fff3bf" data-email-background-color <?= $editable ? '' : 'disabled' ?> aria-label="Choose highlight color">
                                </label>
                                <select data-email-font-size <?= $editable ? '' : 'disabled' ?> aria-label="Font size" title="Font size">
                                    <option value="12">12</option><option value="14">14</option><option value="16" selected>16</option><option value="18">18</option><option value="20">20</option><option value="24">24</option><option value="28">28</option>
                                </select>
                            </div>
                            <div class="email-builder__toolbar-group email-builder__toolbar-insert" aria-label="Insert content">
                                <button type="button" data-email-insert-tool="text" <?= $editable ? '' : 'disabled' ?> title="Insert text" aria-label="Insert text"><?= $editorIcon('text') ?></button>
                                <button type="button" data-email-insert-tool="photo" <?= $editable ? '' : 'disabled' ?> title="Insert photo" aria-label="Insert photo"><?= $editorIcon('photo') ?></button>
                                <button type="button" data-email-insert-tool="video" <?= $editable ? '' : 'disabled' ?> title="Insert video link" aria-label="Insert video link"><?= $editorIcon('video') ?></button>
                                <button type="button" data-email-insert-tool="youtube" <?= $editable ? '' : 'disabled' ?> title="Insert YouTube" aria-label="Insert YouTube"><?= $editorIcon('youtube') ?></button>
                                <button type="button" data-email-insert-tool="link" <?= $editable ? '' : 'disabled' ?> title="Insert link" aria-label="Insert link"><?= $editorIcon('link') ?></button>
                                <button type="button" data-email-insert-tool="media" <?= $editable ? '' : 'disabled' ?> title="Media Library" aria-label="Media Library"><?= $editorIcon('media') ?></button>
                                <button type="button" data-email-source-toggle title="HTML source" aria-label="Toggle HTML source"><?= $editorIcon('code') ?></button>
                            </div>
                            <div class="email-builder__toolbar-group" aria-label="Block actions">
                                <button type="button" data-email-block-action="up" <?= $editable ? '' : 'disabled' ?> title="Move block up" aria-label="Move block up"><?= $editorIcon('up') ?></button>
                                <button type="button" data-email-block-action="down" <?= $editable ? '' : 'disabled' ?> title="Move block down" aria-label="Move block down"><?= $editorIcon('down') ?></button>
                                <button type="button" data-email-block-action="duplicate" <?= $editable ? '' : 'disabled' ?> title="Duplicate block" aria-label="Duplicate block"><?= $editorIcon('copy') ?></button>
                                <button type="button" data-email-block-action="delete" <?= $editable ? '' : 'disabled' ?> title="Delete block" aria-label="Delete block"><?= $editorIcon('trash') ?></button>
                            </div>
                        </div>
                        <div class="email-builder__rich-editor-shell" data-email-editor-shell>
                            <div
                                class="email-builder__rich-editor"
                                data-email-rich-editor
                                contenteditable="<?= $editable ? 'true' : 'false' ?>"
                                role="textbox"
                                aria-multiline="true"
                                aria-label="Email text editor"
                                data-placeholder="Write the email content here…"
                            ></div>
                            <textarea name="html_template" rows="22" maxlength="20000" <?= $editable ? '' : 'readonly' ?> data-email-html data-email-html-source hidden spellcheck="false"><?= e($html) ?></textarea>
                        </div>
                        <footer>
                            <span>Visual content is stored as email-safe HTML with inline formatting. Video and YouTube are inserted as linked media cards for Gmail/Outlook compatibility.</span>
                            <span data-email-html-count></span>
                        </footer>
                    </div>
                </section>

                <section class="email-builder__panel" data-email-builder-panel="plain" hidden>
                    <div class="email-builder__code-card">
                        <header><div><strong>Plain-text version</strong><small>Required for accessibility and text-only mail clients.</small></div><span>10,000 characters max</span></header>
                        <textarea name="text_template" rows="24" maxlength="10000" <?= $editable ? 'required' : 'readonly' ?> data-email-text><?= e($text) ?></textarea>
                        <footer><span>Keep this version readable without HTML formatting.</span><span data-email-text-count></span></footer>
                    </div>
                </section>

                <section class="email-builder__panel" data-email-builder-panel="settings" hidden>
                    <div class="email-builder__settings-grid">
                        <article><small>Email key</small><strong><?= e($createMode ? 'Generated from the Template key field' : $key) ?></strong><p><?= e($createMode ? 'Custom keys are permanent identifiers after creation.' : 'Stable identifier used by the delivery pipeline.') ?></p></article>
                        <article><small>Category</small><strong><?= e($group) ?></strong><p><?= e($description) ?></p></article>
                        <article><small>Security</small><strong><?= e($sensitive ? 'Restricted variables' : ($createMode || str_starts_with($key, 'custom_') ? 'Custom safe variables' : 'Standard template')) ?></strong><p><?= e($sensitive ? 'Test delivery uses sample values; live token values remain delivery-time only.' : ($createMode || str_starts_with($key, 'custom_') ? 'Only the generic Builder variables listed below are accepted.' : 'Uses workflow-generated variables only.')) ?></p></article>
                        <article><small>Current source</small><strong><?= e($createMode ? 'New custom template' : (str_starts_with($key, 'custom_') ? 'Standalone custom template' : (is_array($template) ? 'Custom override' : 'Barama fallback'))) ?></strong><p><?= e($createMode || str_starts_with($key, 'custom_') ? 'Custom templates are reusable and do not replace a governed Barama fallback.' : 'No active override means the original system email is delivered.') ?></p></article>
                    </div>

                    <div class="email-builder__tool-grid">
                        <section class="email-builder__tool-card">
                            <header><div><strong>Send Test Email</strong><small>Uses the unsaved Builder content and safe sample variables.</small></div><?= app_icon('mail') ?></header>
                            <label><span>Recipient email</span><input type="email" data-email-test-recipient placeholder="you@example.com" autocomplete="email" <?= $createMode ? 'disabled' : '' ?>></label>
                            <button type="button" class="email-builder__button email-builder__button--primary" data-email-send-test <?= $createMode ? 'disabled' : '' ?>>Send test email</button>
                            <p data-email-test-status><?= $createMode ? 'Save the custom template first, then test it from its Builder.' : 'Subject will be prefixed with <code>[TEST]</code>.' ?></p>
                        </section>

                        <section class="email-builder__tool-card">
                            <header><div><strong>Default content</strong><small><?= e($createMode || str_starts_with($key, 'custom_') ? 'Restore the clean custom-template starter content.' : "Restore Barama's catalog default into the editor before saving.") ?></small></div><?= app_icon('layers') ?></header>
                            <button type="button" class="email-builder__button email-builder__button--ghost" data-email-reset-defaults <?= $editable ? '' : 'disabled' ?>>Reset editor to default</button>
                            <p><?= e($createMode || str_starts_with($key, 'custom_') ? 'Reset affects only the editor until you save.' : 'This does not change live delivery until you save and activate the override.') ?></p>
                        </section>

                        <section class="email-builder__tool-card email-builder__tool-card--wide">
                            <header><div><strong>Browser drafts</strong><small>Optional local snapshots for scratch editing on this device.</small></div><?= app_icon('edit') ?></header>
                            <div class="email-builder__revision-actions">
                                <button type="button" class="email-builder__button email-builder__button--ghost" data-email-revision-save <?= $editable ? '' : 'disabled' ?>>Save snapshot</button>
                                <button type="button" class="email-builder__button email-builder__button--ghost" data-email-revision-clear <?= $editable ? '' : 'disabled' ?>>Clear snapshots</button>
                            </div>
                            <div class="email-builder__revision-list" data-email-revision-list><p>No browser snapshots yet.</p></div>
                        </section>

                        <section class="email-builder__tool-card email-builder__tool-card--wide">
                            <header><div><strong>Server revision history</strong><small>Immutable snapshots recorded automatically before saves, status changes and restores.</small></div><?= app_icon('layers') ?></header>
                            <div class="email-builder__server-revisions">
                                <?php if ($serverRevisions === []): ?><p>No server revisions recorded for this language yet.</p><?php endif; ?>
                                <?php foreach ($serverRevisions as $revision): ?>
                                    <div class="email-builder__server-revision">
                                        <div><strong>Revision <?= e((string) ($revision['revision_number'] ?? '')) ?></strong><small><?= e((string) ($revision['revision_event'] ?? 'snapshot')) ?> · <?= e((string) ($revision['created_at'] ?? '')) ?><?= !empty($revision['created_by_name']) ? ' · ' . e((string) $revision['created_by_name']) : '' ?></small></div>
                                        <?php if ($editable && is_array($template)): ?>
                                            <form action="<?= e(url('/dashboard/email-templates/' . rawurlencode($key) . '/revisions/' . (int) $revision['id'] . '/restore')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="locale" value="<?= e($locale) ?>"><button class="email-builder__button email-builder__button--ghost" type="submit">Restore</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>

                    <div class="email-builder__variable-reference">
                        <h3>Variable reference</h3>
                        <p><?= e($createMode || str_starts_with($key, 'custom_') ? 'Only these safe generic variables are accepted for custom templates.' : 'Only the variables listed below are accepted for this email type.') ?></p>
                        <div><?php foreach ($variables as $variable): ?><code>{{<?= e((string) $variable) ?>}}</code><?php endforeach; ?></div>
                    </div>
                </section>

            <?php if ($editable): ?>
                </form>
            <?php else: ?>
                </div>
            <?php endif; ?>
        </main>

        <aside class="email-builder__preview">
            <div class="email-builder__preview-head">
                <div><strong>Live preview</strong><small>Safe sample data</small></div>
                <div class="email-builder__preview-modes">
                    <button class="is-active" type="button" data-email-preview-mode="desktop">Desktop</button>
                    <button type="button" data-email-preview-mode="mobile">Mobile</button>
                </div>
            </div>
            <div class="email-builder__preview-subject"><small>Subject</small><strong data-email-preview-subject></strong></div>
            <div class="email-builder__preview-frame" data-email-preview-frame>
                <iframe sandbox title="Email HTML preview" data-email-preview-html></iframe>
            </div>
            <details class="email-builder__text-preview">
                <summary>Plain-text preview</summary>
                <pre data-email-preview-text></pre>
            </details>
            <div class="email-builder__preview-note">Preview has no script access. Test and live delivery use the same generated email HTML. Video and YouTube appear as linked poster cards so Gmail and other major clients preserve the layout.</div>
        </aside>
    </div>


    <div class="email-builder__dialog" data-email-insert-dialog hidden aria-hidden="true">
        <button class="email-builder__dialog-backdrop" type="button" data-email-dialog-close aria-label="Close dialog"></button>
        <section class="email-builder__dialog-card" role="dialog" aria-modal="true" aria-labelledby="email-builder-dialog-title">
            <header>
                <div>
                    <small>Text Editor</small>
                    <strong id="email-builder-dialog-title" data-email-dialog-title>Insert content</strong>
                    <p data-email-dialog-help>Add email-safe content to the current editor position.</p>
                </div>
                <button class="email-builder__dialog-close" type="button" data-email-dialog-close title="Close" aria-label="Close"><?= $editorIcon('close') ?></button>
            </header>

            <div class="email-builder__dialog-form" data-email-dialog-form>
                <label data-email-dialog-field="url"><span data-email-dialog-url-label>URL</span><input type="url" data-email-dialog-url placeholder="https://example.com/"></label>
                <label data-email-dialog-field="label"><span>Display text</span><input type="text" maxlength="180" data-email-dialog-label placeholder="Open link"></label>
                <label data-email-dialog-field="alt"><span>Alt text</span><input type="text" maxlength="255" data-email-dialog-alt placeholder="Describe the image"></label>
                <label data-email-dialog-field="poster"><span>Poster image URL</span><input type="url" data-email-dialog-poster placeholder="https://example.com/poster.jpg"></label>
                <label data-email-dialog-field="click"><span>Optional click URL</span><input type="url" data-email-dialog-click placeholder="https://example.com/"></label>
            </div>

            <div class="email-builder__media-library" data-email-media-library hidden>
                <div class="email-builder__media-library-controls">
                    <label class="email-builder__media-search"><?= $editorIcon('search') ?><input type="search" data-email-media-search placeholder="Search Media Library"></label>
                    <select data-email-media-type aria-label="Media type"><option value="all">Images & videos</option><option value="image">Images</option><option value="video">Videos</option></select>
                </div>
                <div class="email-builder__media-status" data-email-media-status>Loading media…</div>
                <div class="email-builder__media-grid" data-email-media-grid></div>
            </div>

            <footer>
                <span data-email-dialog-email-note>Media is converted to email-safe markup with inline styles.</span>
                <div>
                    <button type="button" class="email-builder__button email-builder__button--ghost" data-email-dialog-close>Cancel</button>
                    <button type="button" class="email-builder__button email-builder__button--primary" data-email-dialog-insert>Insert</button>
                </div>
            </footer>
        </section>
    </div>

    <form hidden data-email-test-csrf><?= csrf_field() ?></form>

    <footer class="email-builder__action-bar">
        <div>
            <strong><?= e($createMode ? 'New custom email template.' : ($status === 'base-required' ? 'Create the base override first.' : ($status === 'fallback' ? ($isBase ? 'System fallback is currently live.' : 'English fallback is currently live for this language.') : 'Template status: ' . ucfirst($status)))) ?></strong>
            <span><?= e($createMode ? 'Create it as inactive, review it, then activate only when it is ready for use.' : ($status === 'inactive' ? 'Save and review before activation.' : ($status === 'active' ? 'Deactivate to unlock editing.' : ($status === 'archived' ? 'Archived templates are read-only.' : 'Create an inactive override without affecting live delivery.')))) ?></span>
        </div>
        <div class="email-builder__actions">
            <a class="email-builder__button email-builder__button--ghost" href="<?= e(url('/dashboard/email-templates')) ?>">Back</a>
            <?php if ($createMode): ?>
                <button class="email-builder__button email-builder__button--primary" type="submit" form="<?= e($formId) ?>">Create inactive template</button>
            <?php elseif ($status === 'fallback'): ?>
                <button class="email-builder__button email-builder__button--primary" type="submit" form="<?= e($formId) ?>"><?= e($isBase ? 'Create inactive override' : 'Create inactive translation') ?></button>
            <?php elseif ($status === 'inactive'): ?>
                <button class="email-builder__button email-builder__button--primary" type="submit" form="<?= e($formId) ?>">Save changes</button>
                <form action="<?= e($statusUrl) ?>" method="post" data-workspace-form>
                    <?= csrf_field() ?><input type="hidden" name="status" value="active">
                    <button class="email-builder__button email-builder__button--success" type="submit">Activate</button>
                </form>
                <form action="<?= e($statusUrl) ?>" method="post" data-workspace-form>
                    <?= csrf_field() ?><input type="hidden" name="status" value="archived">
                    <button class="email-builder__button email-builder__button--danger table-action--danger" type="submit">Archive</button>
                </form>
            <?php elseif ($status === 'active'): ?>
                <form action="<?= e($statusUrl) ?>" method="post" data-workspace-form>
                    <?= csrf_field() ?><input type="hidden" name="status" value="inactive">
                    <button class="email-builder__button email-builder__button--primary" type="submit">Deactivate to edit</button>
                </form>
            <?php endif; ?>
        </div>
    </footer>

    <script type="application/json" data-email-template-samples><?= json_encode($samples, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/json" data-email-template-defaults><?= json_encode($defaults, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
</section>
<script src="<?= e(asset('js/admin-email-template-builder-v143.js') . '?v=143') ?>" defer></script>
