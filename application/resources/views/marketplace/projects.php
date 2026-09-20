<?php
declare(strict_types=1);

$rows = is_array($inventory['rows'] ?? null) ? $inventory['rows'] : [];
$page = (int) ($inventory['page'] ?? 1);
$pages = (int) ($inventory['pages'] ?? 1);
$total = (int) ($inventory['total'] ?? 0);
?>
<section class="marketplace-hero marketplace-hero--compact">
    <div class="container app-container">
        <span class="eyebrow">New Developments</span>
        <div class="marketplace-hero__grid">
            <div>
                <h1>Explore published real estate projects.</h1>
                <p>Browse developments from approved companies by location, project type, and completion stage.</p>
            </div>
            <div class="marketplace-hero__count"><strong><?= e(number_format($total)) ?></strong><span>Published project<?= $total === 1 ? '' : 's' ?></span></div>
        </div>
    </div>
</section>

<section class="marketplace-section">
    <div class="container app-container">
        <form class="marketplace-filter marketplace-filter--projects" method="get">
            <label class="marketplace-filter__search"><span>Search</span><input class="form-control" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Project, developer, location"></label>
            <label><span>Type</span><select class="form-select" name="type"><option value="">All types</option><?php foreach ($projectTypes as $type): ?><option value="<?= e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></label>
            <label><span>Stage</span><select class="form-select" name="completion"><option value="">All stages</option><?php foreach ($completionStatuses as $status): ?><option value="<?= e($status) ?>" <?= ($filters['completion'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></label>
            <label><span>Location</span><select class="form-select" name="location"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>" <?= (string) ($filters['location'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></label>
            <div class="marketplace-filter__actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="<?= e(url('/projects')) ?>">Reset</a></div>
        </form>

        <?php if ($rows === []): ?>
            <div class="marketplace-empty"><strong>No projects match these filters.</strong><span>Published projects will appear here after administrator review.</span></div>
        <?php else: ?>
            <div class="inventory-grid inventory-grid--projects">
                <?php foreach ($rows as $project): ?>
                    <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'project', 'item' => $project]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="marketplace-pagination">
                <span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
                <div>
                    <?php $base = $filters; if ($page > 1): $base['page'] = $page - 1; ?><a href="?<?= e(http_build_query($base)) ?>">Previous</a><?php endif; ?>
                    <?php $base = $filters; if ($page < $pages): $base['page'] = $page + 1; ?><a href="?<?= e(http_build_query($base)) ?>">Next</a><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</section>
