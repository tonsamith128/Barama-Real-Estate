<?php

declare(strict_types=1);

$capabilities = is_array($mediaUploadCapabilities ?? null)
    ? $mediaUploadCapabilities
    : [];
$groups = is_array($capabilities['groups'] ?? null)
    ? $capabilities['groups']
    : [];
$maxFiles = (int) ($capabilities['max_files'] ?? 20);
$maxLabel = (string) ($capabilities['max_label'] ?? '50 MB');
$acceptExtensions = is_array($capabilities['accepted_extensions'] ?? null)
    ? $capabilities['accepted_extensions']
    : [];
$uploadStatus = trim((string) ($_GET['upload_status'] ?? ''));
$uploadError = trim((string) ($_GET['upload_error'] ?? ''));
$disk = (string) ($mediaUploadDisk ?? 'private');
$storageLabel = match ($disk) {
    's3' => 'Amazon S3 (configured active cloud provider)',
    'r2' => 'Cloudflare R2 (configured active cloud provider)',
    default => 'Private local server storage (default)',
};
$maxBytes = (int) ($capabilities['max_bytes'] ?? 52428800);
?>
<link rel="stylesheet" href="<?= e(asset('css/media-library-upload-v135.css') . '?v=135') ?>">
<script src="<?= e(asset('js/media-library-upload-v134.js') . '?v=134a') ?>" defer></script>

<section class="workspace-page media-upload" data-media-upload>
    <header class="media-upload__hero">
        <div>
            <span class="eyebrow">Admin Control Center</span>
            <h1>Upload Media</h1>
            <p>Add reusable images, documents, PDFs, video and music to the Barama Media Library.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/media')) ?>">
            <?= app_icon('image') ?><span>Media Library</span>
        </a>
    </header>

    <?php if ($uploadStatus !== ''): ?>
        <div class="media-upload__notice is-success" role="status">
            <?= app_icon('check') ?>
            <div><strong>Upload complete</strong><span><?= e($uploadStatus) ?></span></div>
        </div>
    <?php endif; ?>

    <?php if ($uploadError !== ''): ?>
        <div class="media-upload__notice is-error" role="alert">
            <?= app_icon('alert') ?>
            <div><strong>Upload could not be completed</strong><span><?= e($uploadError) ?></span></div>
        </div>
    <?php endif; ?>

    <div class="media-upload__summary" aria-label="Upload capabilities">
        <?php foreach ($groups as $name => $formats): ?>
            <article>
                <span class="media-upload__summary-icon"><?= app_icon($name === 'Images' ? 'image' : 'layers') ?></span>
                <div>
                    <strong><?= e((string) $name) ?></strong>
                    <small><?= e(implode(' · ', (array) $formats)) ?></small>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="media-upload__notice" role="status">
        <?= app_icon('lock') ?>
        <div><strong>Upload destination: <?= e($storageLabel) ?></strong>
            <span>Files are private and served through administrator-authorized preview. Cloud storage is used only if activated in Storage settings.</span>
        </div>
    </div>
    <div class="media-upload__notice is-error" role="alert" data-media-upload-client-error hidden></div>

    <form class="media-upload__form" action="<?= e(url('/dashboard/media/upload')) ?>" method="post" enctype="multipart/form-data" data-media-upload-form data-media-max-files="<?= $maxFiles ?>" data-media-max-bytes="<?= $maxBytes ?>">
        <?= csrf_field() ?>
        <div class="media-upload__dropzone" data-media-dropzone tabindex="0" role="button" aria-label="Choose files or drag and drop media">
            <div class="media-upload__drop-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/>
                </svg>
            </div>
            <strong>Drag &amp; drop media here</strong>
            <p>Or choose files from your computer. Upload up to <?= $maxFiles ?> files at once.</p>
            <button class="btn btn-primary" type="button" data-media-choose>Choose files</button>
            <small>Maximum <?= e($maxLabel) ?> per file. Files are MIME-validated and stored privately.</small>
            <input class="media-upload__native-input" type="file" name="media_files[]" multiple required accept="<?= e('.' . implode(',.', $acceptExtensions)) ?>" data-media-input>
        </div>

        <div class="media-upload__selection" data-media-selection hidden>
            <div class="media-upload__selection-head">
                <div><strong>Selected files</strong><small data-media-count>0 files</small></div>
                <button class="btn btn-outline-secondary" type="button" data-media-clear>Clear selection</button>
            </div>
            <div class="media-upload__file-list" data-media-file-list></div>
        </div>

        <footer class="media-upload__footer">
            <div>
                <strong>Reusable Media Library asset</strong>
                <small>New uploads are attached to the Media Library collection, so they are protected from orphan cleanup.</small>
            </div>
            <button class="btn btn-primary media-upload__submit" type="submit" data-media-submit>
                <span data-media-submit-label>Upload files</span>
            </button>
        </footer>
    </form>

    <aside class="media-upload__policy">
        <div><?= app_icon('shield') ?><span>Server-side MIME validation is authoritative. File extensions alone are never trusted.</span></div>
        <div><?= app_icon('lock') ?><span>Assets use Barama private media storage and the existing Media Library database.</span></div>
        <div><?= app_icon('check') ?><span>Upload activity is recorded in the Security Audit.</span></div>
    </aside>
</section>
