<?php
declare(strict_types=1);

$rows = is_array($inventory['rows'] ?? null) ? array_values($inventory['rows']) : [];
$page = max(1, (int) ($inventory['page'] ?? 1));
$pages = max(1, (int) ($inventory['pages'] ?? 1));
$total = max(0, (int) ($inventory['total'] ?? 0));

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
    'Sort' => ($filters['sort'] ?? 'verified') !== 'verified'
        ? match ((string) $filters['sort']) {
            'name' => 'Company name',
            'projects' => 'Most projects',
            'listings' => 'Most listings',
            default => 'Recently verified',
        }
        : '',
]);
?>
<section class="marketplace-hero marketplace-hero--discovery marketplace-hero--companies">
    <div class="container app-container">
        <div class="marketplace-hero__grid">
            <div>
                <span class="eyebrow">Company Directory</span>
                <h1>Discover Approved Companies</h1>
                <p>Explore verified developers, agencies, property companies, and marketplace vendors with published inventory.</p>
            </div>
            <div class="marketplace-hero__count">
                <strong><?= e(number_format($total)) ?></strong>
                <span>Approved compan<?= $total === 1 ? 'y' : 'ies' ?></span>
            </div>
        </div>

        <form class="discovery-filter discovery-filter--compact company-directory-filter" method="get" data-marketplace-filter aria-label="Company directory filters">
            <div class="discovery-filter__primary">
                <label class="discovery-filter__search">
                    <span><?= app_icon('search') ?></span>
                    <input
                        name="q"
                        value="<?= e((string) ($filters['q'] ?? '')) ?>"
                        placeholder="Company, service, or location"
                        aria-label="Search companies"
                    >
                </label>

                <label>
                    <span class="visually-hidden">Company type</span>
                    <select name="type" aria-label="Company type">
                        <option value="">All company types</option>
                        <?php foreach ($companyTypes as $type): ?>
                            <option value="<?= e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>>
                                <?= e(ucwords(str_replace('_', ' ', $type))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span class="visually-hidden">Location</span>
                    <select name="location" aria-label="Company location">
                        <option value="">All locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option
                                value="<?= e((string) $location['id']) ?>"
                                <?= (string) ($filters['location'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>
                            >
                                <?= e((string) $location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span class="visually-hidden">Sort companies</span>
                    <select name="sort" aria-label="Sort companies">
                        <option value="verified" <?= ($filters['sort'] ?? 'verified') === 'verified' ? 'selected' : '' ?>>Recently verified</option>
                        <option value="name" <?= ($filters['sort'] ?? '') === 'name' ? 'selected' : '' ?>>Company name</option>
                        <option value="projects" <?= ($filters['sort'] ?? '') === 'projects' ? 'selected' : '' ?>>Most projects</option>
                        <option value="listings" <?= ($filters['sort'] ?? '') === 'listings' ? 'selected' : '' ?>>Most listings</option>
                    </select>
                </label>

                <button class="btn btn-primary" type="submit">
                    <?= app_icon('search') ?>
                    <span>Search</span>
                </button>
            </div>

            <div class="discovery-filter__links">
                <?php if ($activeFilters !== []): ?>
                    <a href="<?= e(url('/company')) ?>">Clear filters</a>
                <?php endif; ?>
                <a href="<?= e(url('/projects')) ?>">Explore projects</a>
                <a href="<?= e(url('/properties')) ?>">Browse properties</a>
            </div>
        </form>
    </div>
</section>

<section class="marketplace-section marketplace-results company-directory-results">
    <div class="container app-container">
        <div class="marketplace-results__bar">
            <div>
                <strong><?= e(number_format($total)) ?> compan<?= $total === 1 ? 'y' : 'ies' ?></strong>
                <span><?= $activeFilters !== [] ? 'Filtered approved company profiles' : 'All approved public company profiles' ?></span>
            </div>

            <?php if ($activeFilters !== []): ?>
                <div class="filter-chips" aria-label="Active company filters">
                    <?php foreach ($activeFilters as $label => $value): ?>
                        <span><small><?= e($label) ?></small><?= e($value) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($rows === []): ?>
            <div class="marketplace-empty marketplace-empty--premium">
                <?= app_icon('building') ?>
                <strong>No companies match these filters.</strong>
                <span>Try another company type, location, or search term, or return to all approved companies.</span>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/company')) ?>">View all companies</a>
            </div>
        <?php else: ?>
            <div class="inventory-grid inventory-grid--companies">
                <?php foreach ($rows as $company): ?>
                    <?= \Barama\Core\View::partial('components/inventory-card', [
                        'kind' => 'company',
                        'item' => $company,
                    ]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="marketplace-pagination" aria-label="Company directory result pages">
                <span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
                <div>
                    <?php
                    $base = $filters;
                    if ($page > 1):
                        $base['page'] = $page - 1;
                    ?>
                        <a rel="prev" href="?<?= e(http_build_query($base)) ?>">Previous</a>
                    <?php endif; ?>

                    <?php
                    $base = $filters;
                    if ($page < $pages):
                        $base['page'] = $page + 1;
                    ?>
                        <a rel="next" href="?<?= e(http_build_query($base)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</section>
