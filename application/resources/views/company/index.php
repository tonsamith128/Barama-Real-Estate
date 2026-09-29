<?php
declare(strict_types=1);

$rows = is_array($inventory['rows'] ?? null) ? $inventory['rows'] : [];
$page = (int) ($inventory['page'] ?? 1);
$pages = (int) ($inventory['pages'] ?? 1);
$total = (int) ($inventory['total'] ?? 0);
$gridConfig = is_array($pageConfig['grid'] ?? null) ? $pageConfig['grid'] : [];
$gridColumns = max(2, min(4, (int) ($gridConfig['columns'] ?? 3)));
$showResultCount = !array_key_exists('show_result_count', $gridConfig) || !empty($gridConfig['show_result_count']);

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
<?= \Barama\Core\View::partial('components/marketplace-page-hero', [
    'pageKind' => 'companies',
    'pageConfig' => $pageConfig ?? [],
    'total' => $total,
    'filters' => $filters,
    'locations' => $locations,
    'companyTypes' => $companyTypes
]) ?>

<section class="marketplace-section marketplace-results marketplace-results--companies">
    <div class="container app-container">
        <div class="marketplace-results__bar">
            <div><strong><?= e(number_format($total)) ?> compan<?= $total === 1 ? 'y' : 'ies' ?></strong><span><?= $activeFilters !== [] ? 'Filtered approved company profiles' : 'Approved public company profiles' ?></span></div>
            <?php if ($activeFilters !== []): ?><div class="filter-chips filter-chips--companies" aria-label="Active company filters"><?php foreach ($activeFilters as $label => $value): ?><span><small><?= e($label) ?></small><?= e($value) ?></span><?php endforeach; ?></div><?php endif; ?>
        </div>
        <?php if ($rows === []): ?><div class="marketplace-empty marketplace-empty--premium"><?= app_icon('building') ?><strong>No companies match these filters.</strong><span>Try a broader company type or location.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/company')) ?>">View all companies</a></div>
        <?php else: ?><div class="inventory-grid inventory-grid--companies marketplace-config-grid" style="--marketplace-grid-columns: <?= e((string) $gridColumns) ?>"><?php foreach ($rows as $company): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'company', 'item' => $company]) ?><?php endforeach; ?></div><?php endif; ?>
        <?php if ($pages > 1): ?><nav class="marketplace-pagination" aria-label="Company result pages"><span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span><div><?php $base = $filters; if ($page > 1): $base['page'] = $page - 1; ?><a href="?<?= e(http_build_query($base)) ?>">Previous</a><?php endif; ?><?php $base = $filters; if ($page < $pages): $base['page'] = $page + 1; ?><a href="?<?= e(http_build_query($base)) ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
    </div>
</section>
