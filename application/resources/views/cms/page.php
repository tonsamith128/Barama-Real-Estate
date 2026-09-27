<?php

declare(strict_types=1);

$title = (string) ($page['title'] ?? '');
$excerpt = trim((string) ($page['excerpt'] ?? ''));
$contentText = trim((string) ($page['content_text'] ?? ''));
$paragraphs = preg_split('/\R{2,}/u', $contentText) ?: [];
?>
<main class="container app-container py-5">
    <article class="content-page">
        <header class="mb-4">
            <h1><?= e($title) ?></h1>
            <?php if ($excerpt !== ''): ?>
                <p class="lead"><?= e($excerpt) ?></p>
            <?php endif; ?>
        </header>

        <div class="content-page__body">
            <?php foreach ($paragraphs as $paragraph): ?>
                <?php $paragraph = trim((string) $paragraph); ?>
                <?php if ($paragraph !== ''): ?>
                    <p><?= nl2br(e($paragraph)) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </article>
</main>
