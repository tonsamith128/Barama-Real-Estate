<?php

declare(strict_types=1);

$title = trim((string) ($page['title'] ?? ''));
$excerpt = trim((string) ($page['excerpt'] ?? ''));
$contentText = trim((string) ($page['content_text'] ?? ''));
$slug = trim((string) ($page['slug'] ?? ''));
$paragraphs = preg_split('/\R{2,}/u', $contentText) ?: [];

$legalSlugs = [
    'privacy',
    'privacy-policy',
    'terms',
    'terms-of-service',
    'terms-and-conditions',
    'disclaimer',
];
$isLegalPage = in_array($slug, $legalSlugs, true);

$publishedAt = trim((string) ($page['published_at'] ?? ''));
$updatedAt = trim((string) ($page['updated_at'] ?? ''));
$displayDate = $updatedAt !== '' ? $updatedAt : $publishedAt;
$displayDateLabel = '';
$displayDateIso = '';

if ($displayDate !== '') {
    $timestamp = strtotime($displayDate);

    if ($timestamp !== false) {
        $displayDateLabel = date('M j, Y', $timestamp);
        $displayDateIso = date(DATE_ATOM, $timestamp);
    }
}

$wordCount = str_word_count(strip_tags($contentText));
$readingMinutes = max(1, (int) ceil($wordCount / 220));
?>
<main class="cms-page">
    <div class="container app-container">
        <nav class="detail-breadcrumb cms-page__breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('/')) ?>">Home</a>
            <span>/</span>
            <span><?= e($title !== '' ? $title : 'Page') ?></span>
        </nav>

        <article class="content-page" aria-labelledby="cms-page-title">
            <header class="content-page__header">
                <div class="content-page__heading">
                    <span class="eyebrow"><?= e($isLegalPage ? 'Legal information' : 'Managed page') ?></span>
                    <h1 id="cms-page-title"><?= e($title !== '' ? $title : 'Page') ?></h1>

                    <?php if ($excerpt !== ''): ?>
                        <p class="content-page__lead"><?= e($excerpt) ?></p>
                    <?php endif; ?>

                    <div class="content-page__meta" aria-label="Page information">
                        <span><?= app_icon($isLegalPage ? 'shield' : 'file') ?><strong><?= e($isLegalPage ? 'Legal page' : 'Public page') ?></strong></span>
                        <?php if ($displayDateLabel !== ''): ?>
                            <span><?= app_icon('calendar') ?><strong>Updated</strong><time datetime="<?= e($displayDateIso) ?>"><?= e($displayDateLabel) ?></time></span>
                        <?php endif; ?>
                        <?php if ($contentText !== ''): ?>
                            <span><?= app_icon('clock') ?><strong><?= e((string) $readingMinutes) ?> min read</strong></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="content-page__layout">
                <div class="content-page__body">
                    <?php if ($paragraphs === [] || $contentText === ''): ?>
                        <p class="content-page__empty">This page does not contain published content yet.</p>
                    <?php else: ?>
                        <?php foreach ($paragraphs as $paragraph): ?>
                            <?php $paragraph = trim((string) $paragraph); ?>
                            <?php if ($paragraph !== ''): ?>
                                <p><?= nl2br(e($paragraph)) ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <aside class="content-page__aside" aria-label="Page actions">
                    <div>
                        <span class="eyebrow"><?= e($isLegalPage ? 'Legal document' : 'Page') ?></span>
                        <strong><?= e($title !== '' ? $title : 'Published information') ?></strong>
                        <?php if ($displayDateLabel !== ''): ?>
                            <small>Last updated <?= e($displayDateLabel) ?></small>
                        <?php endif; ?>
                    </div>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/')) ?>"><?= app_icon('arrow-left') ?><span>Back to home</span></a>
                </aside>
            </div>
        </article>
    </div>
</main>
