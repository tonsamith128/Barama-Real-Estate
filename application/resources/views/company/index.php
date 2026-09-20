<?php
declare(strict_types=1);

$rows = is_array($inventory['rows'] ?? null) ? $inventory['rows'] : [];
$page = (int) ($inventory['page'] ?? 1);
$pages = (int) ($inventory['pages'] ?? 1);
$total = (int) ($inventory['total'] ?? 0);
?>
<section class="marketplace-hero marketplace-hero--compact">
    <div class="container app-container">
        <span class="eyebrow">Developer Directory</span>
        <div class="marketplace-hero__grid">
            <div>
                <h1>Discover approved real estate companies.</h1>
                <p>Explore developer and agency profiles with published projects and marketplace inventory.</p>
            </div>
            <div class="marketplace-hero__count"><strong><?= e(number_format($total)) ?></strong><span>Approved compan<?= $total === 1 ? 'y' : 'ies' ?></span></div>
        </div>
    </div>
</section>

<section class="marketplace-section">
    <div class="container app-container">
        <form class="marketplace-filter marketplace-filter--companies" method="get">
            <label class="marketplace-filter__search"><span>Search</span><input class="form-control" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Company or location"></label>
            <label><span>Company type</span><select class="form-select" name="type"><option value="">All companies</option><?php foreach ($companyTypes as $type): ?><option value="<?= e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></label>
            <label><span>Location</span><select class="form-select" name="location"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>" <?= (string) ($filters['location'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></label>
            <div class="marketplace-filter__actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="<?= e(url('/company')) ?>">Reset</a></div>
        </form>

        <?php if ($rows === []): ?>
            <div class="marketplace-empty"><strong>No approved companies match these filters.</strong><span>Try a broader search or location.</span></div>
        <?php else: ?>
            <div class="inventory-grid inventory-grid--companies">
                <?php foreach ($rows as $company): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'company', 'item' => $company]) ?><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="marketplace-pagination"><span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span><div>
                <?php $base = $filters; if ($page > 1): $base['page'] = $page - 1; ?><a href="?<?= e(http_build_query($base)) ?>">Previous</a><?php endif; ?>
                <?php $base = $filters; if ($page < $pages): $base['page'] = $page + 1; ?><a href="?<?= e(http_build_query($base)) ?>">Next</a><?php endif; ?>
            </div></nav>
        <?php endif; ?>
    </div>
</section>
