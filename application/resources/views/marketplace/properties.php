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
<section class="marketplace-hero marketplace-hero--discovery">
    <div class="container app-container">
        <div class="marketplace-hero__grid">
            <div>
                <span class="eyebrow">Property Marketplace</span>
                <h1>Find Your Property</h1>
                <p>Search approved sale and rental listings by location, property type, bedrooms, and budget.</p>
            </div>
            <div class="marketplace-hero__count"><strong><?= e(number_format($total)) ?></strong><span>Published listing<?= $total === 1 ? '' : 's' ?></span></div>
        </div>

        <form class="discovery-filter" method="get" data-marketplace-filter aria-label="Property filters">
            <div class="discovery-filter__primary">
                <label class="discovery-filter__search">
                    <span><?= app_icon('search') ?></span>
                    <input name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Property, company, reference, location" aria-label="Search properties">
                </label>
                <label>
                    <span class="visually-hidden">Offer</span>
                    <select name="offer">
                        <option value="">Sale & rent</option>
                        <option value="sale" <?= ($filters['offer'] ?? '') === 'sale' ? 'selected' : '' ?>>For sale</option>
                        <option value="rent" <?= ($filters['offer'] ?? '') === 'rent' ? 'selected' : '' ?>>For rent</option>
                    </select>
                </label>
                <label>
                    <span class="visually-hidden">Location</span>
                    <select name="location">
                        <option value="">All locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= e((string) $location['id']) ?>" <?= (string) ($filters['location'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>><?= e((string) $location['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn btn-primary" type="submit"><?= app_icon('search') ?><span>Search</span></button>
            </div>

            <details class="discovery-filter__more" <?= $advancedFilters !== [] ? 'open' : '' ?>>
                <summary><?= app_icon('grid') ?><span>More filters</span><small><?= e((string) count($advancedFilters)) ?> active</small></summary>
                <div class="discovery-filter__advanced">
                    <label><span>Property type</span><select name="type"><option value="">All types</option><?php foreach ($propertyTypes as $type): ?><option value="<?= e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></label>
                    <label><span>Bedrooms</span><select name="bedrooms"><option value="">Any</option><?php foreach ([1,2,3,4,5] as $bed): ?><option value="<?= e((string) $bed) ?>" <?= (string) ($filters['bedrooms'] ?? '') === (string) $bed ? 'selected' : '' ?>><?= e((string) $bed) ?>+</option><?php endforeach; ?></select></label>
                    <label><span>Min price</span><input type="number" min="0" step="100" name="min_price" value="<?= e((string) ($filters['min_price'] ?? '')) ?>" placeholder="No minimum"></label>
                    <label><span>Max price</span><input type="number" min="0" step="100" name="max_price" value="<?= e((string) ($filters['max_price'] ?? '')) ?>" placeholder="No maximum"></label>
                    <label><span>Currency</span><input name="currency" maxlength="3" value="<?= e((string) ($filters['currency'] ?? '')) ?>" placeholder="Any"></label>
                    <label><span>Sort</span><select name="sort"><option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest</option><option value="price_asc" <?= ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option><option value="price_desc" <?= ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option><option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest</option></select></label>
                    <div class="discovery-filter__advanced-actions"><button class="btn btn-primary btn-sm" type="submit">Apply filters</button><a href="<?= e(url('/properties')) ?>">Clear all</a></div>
                </div>
            </details>
        </form>
    </div>
</section>

<section class="marketplace-section marketplace-results">
    <div class="container app-container">
        <div class="marketplace-results__bar">
            <div><strong><?= e(number_format($total)) ?> result<?= $total === 1 ? '' : 's' ?></strong><span><?= $resultFilters !== [] ? 'Filtered marketplace inventory' : 'All approved marketplace inventory' ?></span></div>
            <?php if ($resultFilters !== []): ?><div class="filter-chips" aria-label="Active property filters"><?php foreach ($resultFilters as $label => $value): ?><span><small><?= e($label) ?></small><?= e($value) ?></span><?php endforeach; ?></div><?php endif; ?>
        </div>

        <?php if ($rows === []): ?>
            <div class="marketplace-empty marketplace-empty--premium"><?= app_icon('search') ?><strong>No properties match this search.</strong><span>Try removing a filter, widening the price range, or browsing all approved inventory.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/properties')) ?>">View all properties</a></div>
        <?php else: ?>
            <div class="inventory-grid"><?php foreach ($rows as $listing): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $listing]) ?><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="marketplace-pagination" aria-label="Property result pages"><span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span><div>
                <?php $base = $filters; if ($page > 1): $base['page'] = $page - 1; ?><a href="?<?= e(http_build_query($base)) ?>">Previous</a><?php endif; ?>
                <?php $base = $filters; if ($page < $pages): $base['page'] = $page + 1; ?><a href="?<?= e(http_build_query($base)) ?>">Next</a><?php endif; ?>
            </div></nav>
        <?php endif; ?>
    </div>
</section>
