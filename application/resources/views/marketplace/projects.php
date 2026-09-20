<?php
declare(strict_types=1);

$rows = is_array($inventory['rows'] ?? null) ? $inventory['rows'] : [];
$page = (int) ($inventory['page'] ?? 1);
$pages = (int) ($inventory['pages'] ?? 1);
$total = (int) ($inventory['total'] ?? 0);
?>
<section class="marketplace-hero marketplace-hero--discovery marketplace-hero--projects">
    <div class="container app-container">
        <div class="marketplace-hero__grid">
            <div><span class="eyebrow">New Developments</span><h1>Explore projects from approved real estate companies.</h1><p>Discover published developments by location, project type, and construction stage.</p></div>
            <div class="marketplace-hero__count"><strong><?= e(number_format($total)) ?></strong><span>Published project<?= $total === 1 ? '' : 's' ?></span></div>
        </div>

        <form class="discovery-filter discovery-filter--compact" method="get" data-marketplace-filter>
            <div class="discovery-filter__primary">
                <label class="discovery-filter__search"><span><?= app_icon('search') ?></span><input name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Project, company, location"></label>
                <label><span class="visually-hidden">Type</span><select name="type"><option value="">All project types</option><?php foreach ($projectTypes as $type): ?><option value="<?= e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></label>
                <label><span class="visually-hidden">Location</span><select name="location"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>" <?= (string) ($filters['location'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></label>
                <label><span class="visually-hidden">Stage</span><select name="completion"><option value="">All stages</option><?php foreach ($completionStatuses as $status): ?><option value="<?= e($status) ?>" <?= ($filters['completion'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-primary" type="submit"><?= app_icon('search') ?><span>Search</span></button>
            </div>
            <div class="discovery-filter__links"><a href="<?= e(url('/projects')) ?>">Clear filters</a><a href="<?= e(url('/company')) ?>">Browse companies</a></div>
        </form>
    </div>
</section>

<section class="marketplace-section marketplace-results">
    <div class="container app-container">
        <div class="marketplace-results__bar"><div><strong><?= e(number_format($total)) ?> development<?= $total === 1 ? '' : 's' ?></strong><span>Approved and published projects</span></div></div>
        <?php if ($rows === []): ?>
            <div class="marketplace-empty marketplace-empty--premium"><?= app_icon('layers') ?><strong>No projects match these filters.</strong><span>Try another location, stage, or project type.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/projects')) ?>">View all projects</a></div>
        <?php else: ?>
            <div class="inventory-grid inventory-grid--projects"><?php foreach ($rows as $project): ?><?= BaramaCoreView::partial('components/inventory-card', ['kind' => 'project', 'item' => $project]) ?><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if ($pages > 1): ?><nav class="marketplace-pagination"><span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span><div>
            <?php $base = $filters; if ($page > 1): $base['page'] = $page - 1; ?><a href="?<?= e(http_build_query($base)) ?>">Previous</a><?php endif; ?>
            <?php $base = $filters; if ($page < $pages): $base['page'] = $page + 1; ?><a href="?<?= e(http_build_query($base)) ?>">Next</a><?php endif; ?>
        </div></nav><?php endif; ?>
    </div>
</section>
