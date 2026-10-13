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

$advancedFilters = array_filter([
    'Type' => ($filters['type'] ?? '') !== '' ? ucwords(str_replace('_', ' ', (string) $filters['type'])) : '',
    'Bedrooms' => ($filters['bedrooms'] ?? '') !== '' ? (string) $filters['bedrooms'] . '+' : '',
    'Currency' => (string) ($filters['currency'] ?? ''),
    'Min' => ($filters['min_price'] ?? '') !== '' ? number_format((float) $filters['min_price'], 0) : '',
    'Max' => ($filters['max_price'] ?? '') !== '' ? number_format((float) $filters['max_price'], 0) : '',
    'Sort' => ($filters['sort'] ?? 'newest') !== 'newest'
        ? ucwords(str_replace('_', ' ', (string) $filters['sort']))
        : '',
]);

$resultFilters = array_filter(array_merge([
    'Search' => trim((string) ($filters['q'] ?? '')),
    'Offer' => ($filters['offer'] ?? '') !== '' ? ucwords((string) $filters['offer']) : '',
    'Location' => $locationLabels[(string) ($filters['location'] ?? '')] ?? '',
], $advancedFilters));
?>
<?= \Barama\Core\View::partial('components/marketplace-page-hero', [
    'pageKind' => 'properties',
    'pageConfig' => $pageConfig ?? [],
    'total' => $total,
    'filters' => $filters,
    'locations' => $locations,
    'propertyTypes' => $propertyTypes
]) ?>

<section class="marketplace-section marketplace-results">
    <div class="container app-container">
        <div class="marketplace-results__bar">
            <div><strong><?= e(number_format($total)) ?> result<?= $total === 1 ? '' : 's' ?></strong><span><?= $resultFilters !== [] ? 'Filtered marketplace inventory' : 'All approved marketplace inventory' ?></span></div>
            <?php if ($resultFilters !== []): ?><div class="filter-chips" aria-label="Active property filters"><?php foreach ($resultFilters as $label => $value): ?><span><small><?= e($label) ?></small><?= e($value) ?></span><?php endforeach; ?></div><?php endif; ?>
        </div>

        <?php if ($rows === []): ?>
            <div class="marketplace-empty marketplace-empty--premium"><?= app_icon('search') ?><strong>No properties match this search.</strong><span>Try removing a filter, widening the price range, or browsing all approved inventory.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/properties')) ?>">View all properties</a></div>
        <?php else: ?>
            <div class="inventory-grid marketplace-config-grid" data-barama-marketplace-grid-columns="<?= e((string) $gridColumns) ?>"><?php foreach ($rows as $listing): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $listing]) ?><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="marketplace-pagination" aria-label="Property result pages"><span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span><div>
                <?php $base = $filters; if ($page > 1): $base['page'] = $page - 1; ?><a href="?<?= e(http_build_query($base)) ?>">Previous</a><?php endif; ?>
                <?php $base = $filters; if ($page < $pages): $base['page'] = $page + 1; ?><a href="?<?= e(http_build_query($base)) ?>">Next</a><?php endif; ?>
            </div></nav>
        <?php endif; ?>
    </div>
</section>
