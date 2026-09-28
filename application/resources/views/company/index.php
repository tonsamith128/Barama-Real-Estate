<?php
declare(strict_types=1);

$rows = is_array($inventory['rows'] ?? null) ? $inventory['rows'] : [];
$page = (int) ($inventory['page'] ?? 1);
$pages = (int) ($inventory['pages'] ?? 1);
$total = (int) ($inventory['total'] ?? 0);

$locationLabels = [];
foreach ($locations as $location) {
    $locationLabels[(string) ($location['id'] ?? '')] = (string) ($location['name'] ?? '');
}

$activeFilters = array_filter([
    'Search' => trim((string) ($filters['q'] ?? '')),
    'Type' => ($filters['type'] ?? '') !== ''
        ? ucwords(str_replace('_', ' ', (string) $filters['type']))
        : '',
    'Location' => $locationLabels[(string) ($filters['location'] ?? '')] ?? '',
]);
?>
<section class="marketplace-hero marketplace-hero--discovery marketplace-hero--companies">
    <div class="container app-container">
        <div class="marketplace-hero__grid">
            <div><span class="eyebrow">Company Directory</span><h1>Discover Approved Companies</h1><p>Explore public profiles with published projects and active marketplace inventory.</p></div>
            <div class="marketplace-hero__count"><strong><?= e(number_format($total)) ?></strong><span>Approved compan<?= $total === 1 ? 'y' : 'ies' ?></span></div>
        </div>

        <form class="discovery-filter discovery-filter--compact" method="get" data-marketplace-filter>
            <div class="discovery-filter__primary">
                <label class="discovery-filter__search"><span><?= app_icon('search') ?></span><input name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Company or location" aria-label="Search companies"></label>
                <label><span class="visually-hidden">Company type</span><select name="type"><option value="">All company types</option><?php foreach ($companyTypes as $type): ?><option value="<?= e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></label>
                <label><span class="visually-hidden">Location</span><select name="location"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>" <?= (string) ($filters['location'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-primary" type="submit"><?= app_icon('search') ?><span>Search</span></button>
            </div>
            <div class="discovery-filter__links"><a href="<?= e(url('/company')) ?>">Clear filters</a><a href="<?= e(url('/projects')) ?>">Explore projects</a></div>
        </form>
    </div>
</section>

<section class="marketplace-section marketplace-results marketplace-results--companies">
    <div class="container app-container">
        <div class="marketplace-results__bar">
            <div><strong><?= e(number_format($total)) ?> compan<?= $total === 1 ? 'y' : 'ies' ?></strong><span><?= $activeFilters !== [] ? 'Filtered approved company profiles' : 'Approved public company profiles' ?></span></div>
            <?php if ($activeFilters !== []): ?><div class="filter-chips filter-chips--companies" aria-label="Active company filters"><?php foreach ($activeFilters as $label => $value): ?><span><small><?= e($label) ?></small><?= e($value) ?></span><?php endforeach; ?></div><?php endif; ?>
        </div>
        <?php if ($rows === []): ?><div class="marketplace-empty marketplace-empty--premium"><?= app_icon('building') ?><strong>No companies match these filters.</strong><span>Try a broader company type or location.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/company')) ?>">View all companies</a></div>
        <?php else: ?><div class="inventory-grid inventory-grid--companies"><?php foreach ($rows as $company): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'company', 'item' => $company]) ?><?php endforeach; ?></div><?php endif; ?>
        <?php if ($pages > 1): ?><nav class="marketplace-pagination" aria-label="Company result pages"><span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span><div><?php $base = $filters; if ($page > 1): $base['page'] = $page - 1; ?><a href="?<?= e(http_build_query($base)) ?>">Previous</a><?php endif; ?><?php $base = $filters; if ($page < $pages): $base['page'] = $page + 1; ?><a href="?<?= e(http_build_query($base)) ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
    </div>
</section>
