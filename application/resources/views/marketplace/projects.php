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
    'Stage' => ($filters['completion'] ?? '') !== ''
        ? ucwords(str_replace('_', ' ', (string) $filters['completion']))
        : '',
]);
?>
<?= \Barama\Core\View::partial('components/marketplace-page-hero', [
    'pageKind' => 'projects',
    'pageConfig' => $pageConfig ?? [],
    'total' => $total,
    'filters' => $filters,
    'locations' => $locations,
    'projectTypes' => $projectTypes, 'completionStatuses' => $completionStatuses
]) ?>

<section class="marketplace-section marketplace-results">
    <div class="container app-container">
        <div class="marketplace-results__bar">
            <div><strong><?= e(number_format($total)) ?> development<?= $total === 1 ? '' : 's' ?></strong><span><?= $activeFilters !== [] ? 'Filtered approved projects' : 'Approved and published projects' ?></span></div>
            <?php if ($activeFilters !== []): ?><div class="filter-chips" aria-label="Active project filters"><?php foreach ($activeFilters as $label => $value): ?><span><small><?= e($label) ?></small><?= e($value) ?></span><?php endforeach; ?></div><?php endif; ?>
        </div>
        <?php if ($rows === []): ?>
            <div class="marketplace-empty marketplace-empty--premium"><?= app_icon('layers') ?><strong>No projects match these filters.</strong><span>Try another location, stage, or project type.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/projects')) ?>">View all projects</a></div>
        <?php else: ?>
            <div class="inventory-grid inventory-grid--projects marketplace-config-grid" data-barama-marketplace-grid-columns="<?= e((string) $gridColumns) ?>"><?php foreach ($rows as $project): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'project', 'item' => $project]) ?><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if ($pages > 1): ?><nav class="marketplace-pagination" aria-label="Project result pages"><span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span><div>
            <?php $base = $filters; if ($page > 1): $base['page'] = $page - 1; ?><a href="?<?= e(http_build_query($base)) ?>">Previous</a><?php endif; ?>
            <?php $base = $filters; if ($page < $pages): $base['page'] = $page + 1; ?><a href="?<?= e(http_build_query($base)) ?>">Next</a><?php endif; ?>
        </div></nav><?php endif; ?>
    </div>
</section>
