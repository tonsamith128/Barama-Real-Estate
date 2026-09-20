<?php
declare(strict_types=1);

$rows = is_array($inventory['rows'] ?? null) ? $inventory['rows'] : [];
$page = (int) ($inventory['page'] ?? 1);
$pages = (int) ($inventory['pages'] ?? 1);
$total = (int) ($inventory['total'] ?? 0);
?>
<section class="marketplace-hero marketplace-hero--compact">
    <div class="container app-container">
        <span class="eyebrow">Property Marketplace</span>
        <div class="marketplace-hero__grid">
            <div>
                <h1>Find property for sale and rent.</h1>
                <p>Search verified marketplace inventory by location, property type, bedrooms, and price.</p>
            </div>
            <div class="marketplace-hero__count">
                <strong><?= e(number_format($total)) ?></strong>
                <span>Published listing<?= $total === 1 ? '' : 's' ?></span>
            </div>
        </div>
    </div>
</section>

<section class="marketplace-section">
    <div class="container app-container">
        <form class="marketplace-filter" method="get" aria-label="Property filters">
            <label class="marketplace-filter__search">
                <span>Search</span>
                <input class="form-control" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Property, developer, location">
            </label>
            <label>
                <span>Offer</span>
                <select class="form-select" name="offer">
                    <option value="">Sale & rent</option>
                    <option value="sale" <?= ($filters['offer'] ?? '') === 'sale' ? 'selected' : '' ?>>For sale</option>
                    <option value="rent" <?= ($filters['offer'] ?? '') === 'rent' ? 'selected' : '' ?>>For rent</option>
                </select>
            </label>
            <label>
                <span>Type</span>
                <select class="form-select" name="type">
                    <option value="">All property types</option>
                    <?php foreach ($propertyTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>>
                            <?= e(ucwords(str_replace('_', ' ', $type))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Location</span>
                <select class="form-select" name="location">
                    <option value="">All locations</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= e((string) $location['id']) ?>" <?= (string) ($filters['location'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>>
                            <?= e((string) $location['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Bedrooms</span>
                <select class="form-select" name="bedrooms">
                    <option value="">Any</option>
                    <?php foreach ([1,2,3,4,5] as $bed): ?>
                        <option value="<?= e((string) $bed) ?>" <?= (string) ($filters['bedrooms'] ?? '') === (string) $bed ? 'selected' : '' ?>>
                            <?= e((string) $bed) ?>+
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Min price</span>
                <input class="form-control" type="number" min="0" step="100" name="min_price" value="<?= e((string) ($filters['min_price'] ?? '')) ?>" placeholder="0">
            </label>
            <label>
                <span>Max price</span>
                <input class="form-control" type="number" min="0" step="100" name="max_price" value="<?= e((string) ($filters['max_price'] ?? '')) ?>" placeholder="Any">
            </label>
            <label>
                <span>Currency</span>
                <input class="form-control" name="currency" maxlength="3" value="<?= e((string) ($filters['currency'] ?? '')) ?>" placeholder="Any">
            </label>
            <label>
                <span>Sort</span>
                <select class="form-select" name="sort">
                    <option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="price_asc" <?= ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                    <option value="price_desc" <?= ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
                    <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                </select>
            </label>
            <div class="marketplace-filter__actions">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/properties')) ?>">Reset</a>
            </div>
        </form>

        <?php if ($rows === []): ?>
            <div class="marketplace-empty">
                <strong>No published properties match these filters.</strong>
                <span>Try a broader location, property type, or price range.</span>
            </div>
        <?php else: ?>
            <div class="inventory-grid">
                <?php foreach ($rows as $listing): ?>
                    <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $listing]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="marketplace-pagination" aria-label="Property results pages">
                <span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
                <div>
                    <?php
                    $base = $filters;
                    if ($page > 1):
                        $base['page'] = $page - 1;
                    ?>
                        <a href="?<?= e(http_build_query($base)) ?>">Previous</a>
                    <?php endif; ?>
                    <?php
                    $base = $filters;
                    if ($page < $pages):
                        $base['page'] = $page + 1;
                    ?>
                        <a href="?<?= e(http_build_query($base)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</section>
