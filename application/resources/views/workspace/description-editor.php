<?php

declare(strict_types=1);

$value = (string) ($value ?? '');
$editorId = (string) ($editorId ?? 'workspace-description-editor');
$editorId = preg_replace('/[^A-Za-z0-9_-]/', '-', $editorId) ?: 'workspace-description-editor';
$labelId = $editorId . '-label';

$icons = [
    'undo' => '<path d="M9 7 4 12l5 5"/><path d="M4 12h9a7 7 0 0 1 7 7"/>',
    'redo' => '<path d="m15 7 5 5-5 5"/><path d="M20 12h-9a7 7 0 0 0-7 7"/>',
    'paragraph' => '<path d="M13 5v14M17 5v14M13 5H9a4 4 0 0 0 0 8h4"/>',
    'heading' => '<path d="M4 6v12M12 6v12M4 12h8M16 18h5M18.5 6v12"/>',
    'subheading' => '<path d="M4 7v10M11 7v10M4 12h7M15 9h5l-4 4h4"/>',
    'bold' => '<path d="M7 5h6a4 4 0 0 1 0 8H7z"/><path d="M7 13h7a3.5 3.5 0 0 1 0 7H7z"/>',
    'italic' => '<path d="M10 5h7M7 19h7M14 5 10 19"/>',
    'underline' => '<path d="M6 4v7a6 6 0 0 0 12 0V4M4 21h16"/>',
    'strike' => '<path d="M17 5.5A6 6 0 0 0 12 4c-3 0-5 1.5-5 3.5S9 11 12 11c3 0 5 1.5 5 3.5S15 20 12 20a7 7 0 0 1-5.5-2.5M4 12h16"/>',
    'quote' => '<path d="M6 7h5v5H8a4 4 0 0 1-4 4M15 7h5v5h-3a4 4 0 0 1-4 4"/>',
    'align-left' => '<path d="M4 6h16M4 10h11M4 14h16M4 18h11"/>',
    'align-center' => '<path d="M4 6h16M7 10h10M4 14h16M7 18h10"/>',
    'align-right' => '<path d="M4 6h16M9 10h11M4 14h16M9 18h11"/>',
    'align-justify' => '<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
    'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r=".8"/><circle cx="4.5" cy="12" r=".8"/><circle cx="4.5" cy="18" r=".8"/>',
    'ordered' => '<path d="M10 6h10M10 12h10M10 18h10M4 5h2v3M4 11h2l-2 3h2M4 17h2l-2 3h2"/>',
    'outdent' => '<path d="M10 6h10M10 12h10M10 18h10M3 12l4-4v8z"/>',
    'indent' => '<path d="M10 6h10M10 12h10M10 18h10M7 12 3 8v8z"/>',
    'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.2 1.2M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.2-1.2"/>',
    'unlink' => '<path d="m5 5 14 14M9 15l-1.5 1.5a4 4 0 0 1-5.5-5.5L4 9M15 9l1.5-1.5A4 4 0 0 1 22 13l-2 2"/>',
    'rule' => '<path d="M4 12h16"/>',
    'clear' => '<path d="m4 17 9-9 5 5-7 7H7z"/><path d="M14 20h6M11 6l2-2 7 7-2 2"/>',
];

$icon = static function (string $name) use ($icons): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true">'
        . ($icons[$name] ?? '')
        . '</svg>';
};
?>
<div class="workspace-rich-field" data-rich-text-editor>
    <span class="workspace-rich-field__label" id="<?= e($labelId) ?>">Description</span>

    <textarea
        class="form-control workspace-rich-editor__source"
        name="description"
        rows="7"
        maxlength="10000"
        data-rich-text-source
        aria-labelledby="<?= e($labelId) ?>"
    ><?= e($value) ?></textarea>

    <div class="workspace-rich-editor" data-rich-text-shell hidden>
        <div
            class="workspace-rich-toolbar"
            data-rich-text-toolbar
            role="toolbar"
            aria-label="Description formatting"
        >
            <ul class="workspace-rich-toolbar__list">
                <li><button type="button" data-rich-command="undo" aria-label="Undo" title="Undo"><?= $icon('undo') ?></button></li>
                <li><button type="button" data-rich-command="redo" aria-label="Redo" title="Redo"><?= $icon('redo') ?></button></li>
                <li class="workspace-rich-toolbar__separator" aria-hidden="true"></li>

                <li><button type="button" data-rich-block="p" data-rich-toggle aria-pressed="false" aria-label="Paragraph" title="Paragraph"><?= $icon('paragraph') ?></button></li>
                <li><button type="button" data-rich-block="h3" data-rich-toggle aria-pressed="false" aria-label="Heading" title="Heading"><?= $icon('heading') ?></button></li>
                <li><button type="button" data-rich-block="h4" data-rich-toggle aria-pressed="false" aria-label="Subheading" title="Subheading"><?= $icon('subheading') ?></button></li>
                <li><button type="button" data-rich-block="blockquote" data-rich-toggle aria-pressed="false" aria-label="Block quote" title="Block quote"><?= $icon('quote') ?></button></li>
                <li class="workspace-rich-toolbar__separator" aria-hidden="true"></li>

                <li><button type="button" data-rich-command="bold" data-rich-toggle aria-pressed="false" aria-label="Bold" title="Bold"><?= $icon('bold') ?></button></li>
                <li><button type="button" data-rich-command="italic" data-rich-toggle aria-pressed="false" aria-label="Italic" title="Italic"><?= $icon('italic') ?></button></li>
                <li><button type="button" data-rich-command="underline" data-rich-toggle aria-pressed="false" aria-label="Underline" title="Underline"><?= $icon('underline') ?></button></li>
                <li><button type="button" data-rich-command="strikeThrough" data-rich-toggle aria-pressed="false" aria-label="Strikethrough" title="Strikethrough"><?= $icon('strike') ?></button></li>
                <li class="workspace-rich-toolbar__separator" aria-hidden="true"></li>

                <li><button type="button" data-rich-command="justifyLeft" data-rich-toggle aria-pressed="false" aria-label="Align left" title="Align left"><?= $icon('align-left') ?></button></li>
                <li><button type="button" data-rich-command="justifyCenter" data-rich-toggle aria-pressed="false" aria-label="Align center" title="Align center"><?= $icon('align-center') ?></button></li>
                <li><button type="button" data-rich-command="justifyRight" data-rich-toggle aria-pressed="false" aria-label="Align right" title="Align right"><?= $icon('align-right') ?></button></li>
                <li><button type="button" data-rich-command="justifyFull" data-rich-toggle aria-pressed="false" aria-label="Justify" title="Justify"><?= $icon('align-justify') ?></button></li>
                <li class="workspace-rich-toolbar__separator" aria-hidden="true"></li>

                <li><button type="button" data-rich-command="insertUnorderedList" data-rich-toggle aria-pressed="false" aria-label="Bulleted list" title="Bulleted list"><?= $icon('list') ?></button></li>
                <li><button type="button" data-rich-command="insertOrderedList" data-rich-toggle aria-pressed="false" aria-label="Numbered list" title="Numbered list"><?= $icon('ordered') ?></button></li>
                <li><button type="button" data-rich-command="outdent" aria-label="Outdent" title="Outdent"><?= $icon('outdent') ?></button></li>
                <li><button type="button" data-rich-command="indent" aria-label="Indent" title="Indent"><?= $icon('indent') ?></button></li>
                <li class="workspace-rich-toolbar__separator" aria-hidden="true"></li>

                <li><button type="button" data-rich-command="createLink" aria-label="Add link" title="Add link"><?= $icon('link') ?></button></li>
                <li><button type="button" data-rich-command="unlink" aria-label="Remove link" title="Remove link"><?= $icon('unlink') ?></button></li>
                <li><button type="button" data-rich-command="insertHorizontalRule" aria-label="Horizontal rule" title="Horizontal rule"><?= $icon('rule') ?></button></li>
                <li><button type="button" data-rich-command="removeFormat" aria-label="Clear formatting" title="Clear formatting"><?= $icon('clear') ?></button></li>
            </ul>
        </div>

        <div
            class="workspace-rich-editor__surface"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            aria-labelledby="<?= e($labelId) ?>"
            spellcheck="true"
            data-rich-text-surface
        ><?= \Barama\Support\RichText::render($value) ?></div>
    </div>
</div>
