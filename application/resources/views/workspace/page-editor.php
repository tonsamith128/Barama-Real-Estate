<?php

declare(strict_types=1);

if (($workspaceType ?? '') !== 'admin' || ($recordResource ?? '') !== 'page') {
    return;
}
?>
<section class="workspace-record-panel">
    <div class="workspace-record-panel__head">
        <h2>Page content & SEO</h2>
        <span>Content is stored as plain text in this foundation release and safely escaped on the public page.</span>
    </div>

    <form action="<?= e(url('/dashboard/pages/' . (int) $record['id'])) ?>" method="post" data-workspace-form class="workspace-admin-moderation__form">
        <?= csrf_field() ?>

        <label>
            <span>Page title</span>
            <input class="form-control" name="title" value="<?= e((string) ($record['title'] ?? '')) ?>" maxlength="191" required>
        </label>

        <label>
            <span>URL slug</span>
            <input class="form-control" name="slug" value="<?= e((string) ($record['slug'] ?? '')) ?>" maxlength="160" required>
        </label>

        <label class="workspace-admin-moderation__note">
            <span>Excerpt</span>
            <textarea class="form-control" name="excerpt" rows="3" maxlength="500" placeholder="Optional short introduction"><?= e((string) ($record['excerpt'] ?? '')) ?></textarea>
        </label>

        <label class="workspace-admin-moderation__note">
            <span>Page content</span>
            <textarea class="form-control" name="content_text" rows="14" maxlength="100000" placeholder="Write the public page content here."><?= e((string) ($record['content_text'] ?? '')) ?></textarea>
        </label>

        <label>
            <span>SEO title</span>
            <input class="form-control" name="seo_title" value="<?= e((string) ($record['seo_title'] ?? '')) ?>" maxlength="191" placeholder="Defaults to page title">
        </label>

        <label class="workspace-admin-moderation__note">
            <span>SEO description</span>
            <textarea class="form-control" name="seo_description" rows="3" maxlength="300" placeholder="Defaults to excerpt or page content"><?= e((string) ($record['seo_description'] ?? '')) ?></textarea>
        </label>

        <label>
            <span>Robots directive</span>
            <select class="form-select" name="robots_directive">
                <?php foreach (['index,follow', 'noindex,follow', 'noindex,nofollow'] as $robotsOption): ?>
                    <option value="<?= e($robotsOption) ?>" <?= (string) ($record['robots_directive'] ?? 'index,follow') === $robotsOption ? 'selected' : '' ?>>
                        <?= e($robotsOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save page</button>
    </form>
</section>
