<?php
declare(strict_types=1);

$marketStats = is_array($marketStats ?? null) ? $marketStats : [];
?>
<section class="market-home">
    <div class="container app-container">
        <div class="market-home__hero">
            <div class="market-home__copy">
                <span class="eyebrow">Barama Real Estate</span>
                <h1>Property discovery built around what you want to do.</h1>
                <p>Find approved homes, rental opportunities, new developments, and real estate companies without sorting through unnecessary clutter.</p>

                <form class="market-searchbar" action="<?= e(url('/properties')) ?>" method="get" data-marketplace-filter>
                    <label class="market-searchbar__query">
                        <span><?= app_icon('search') ?></span>
                        <input name="q" placeholder="Search property, company, or location" aria-label="Search property, company, or location">
                    </label>
                    <label>
                        <span class="visually-hidden">Offer</span>
                        <select name="offer" aria-label="Offer">
                            <option value="">Sale & rent</option>
                            <option value="sale">For sale</option>
                            <option value="rent">For rent</option>
                        </select>
                    </label>
                    <label>
                        <span class="visually-hidden">Location</span>
                        <select name="location" aria-label="Location">
                            <option value="">All locations</option>
                            <?php foreach (array_slice($locations, 0, 100) as $location): ?>
                                <option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">
                        <span>Search</span>
                        <?= app_icon('search') ?>
                    </button>
                </form>

                <div class="market-home__actions">
                    <a href="<?= e(url('/projects')) ?>"><?= app_icon('layers') ?><span>Explore developments</span></a>
                    <a href="<?= e(url('/company')) ?>"><?= app_icon('building') ?><span>Browse companies</span></a>
                </div>
            </div>

            <aside class="market-pulse" aria-label="Marketplace overview">
                <div class="market-pulse__glow" aria-hidden="true"></div>
                <div class="market-pulse__head">
                    <span><?= app_icon('home') ?></span>
                    <div><strong>Marketplace</strong><small>Approved public inventory</small></div>
                </div>
                <div class="market-pulse__stats">
                    <a href="<?= e(url('/properties?offer=sale')) ?>"><strong><?= e(number_format((int) ($marketStats['sale'] ?? 0))) ?></strong><span>For sale</span></a>
                    <a href="<?= e(url('/properties?offer=rent')) ?>"><strong><?= e(number_format((int) ($marketStats['rent'] ?? 0))) ?></strong><span>For rent</span></a>
                    <a href="<?= e(url('/projects')) ?>"><strong><?= e(number_format((int) ($marketStats['projects'] ?? 0))) ?></strong><span>Projects</span></a>
                    <a href="<?= e(url('/company')) ?>"><strong><?= e(number_format((int) ($marketStats['companies'] ?? 0))) ?></strong><span>Companies</span></a>
                </div>
                <div class="market-pulse__note"><?= app_icon('shield') ?><span>Public records appear only after marketplace approval.</span></div>
            </aside>
        </div>

        <div class="market-intent-grid" aria-label="Explore marketplace">
            <a class="market-intent market-intent--sale" href="<?= e(url('/properties?offer=sale')) ?>">
                <span class="market-intent__icon"><?= app_icon('home') ?></span>
                <span><strong>Buy property</strong><small>Browse published homes and investment inventory.</small></span>
                <span class="market-intent__arrow">↗</span>
            </a>
            <a class="market-intent market-intent--rent" href="<?= e(url('/properties?offer=rent')) ?>">
                <span class="market-intent__icon"><?= app_icon('key') ?></span>
                <span><strong>Rent property</strong><small>Find rental listings by location and budget.</small></span>
                <span class="market-intent__arrow">↗</span>
            </a>
            <a class="market-intent market-intent--project" href="<?= e(url('/projects')) ?>">
                <span class="market-intent__icon"><?= app_icon('layers') ?></span>
                <span><strong>New developments</strong><small>Explore published projects and available units.</small></span>
                <span class="market-intent__arrow">↗</span>
            </a>
            <a class="market-intent market-intent--company" href="<?= e(url('/company')) ?>">
                <span class="market-intent__icon"><?= app_icon('building') ?></span>
                <span><strong>Companies</strong><small>Discover approved developers and agencies.</small></span>
                <span class="market-intent__arrow">↗</span>
            </a>
        </div>
    </div>
</section>

<?php if ($latestListings !== []): ?>
<section class="marketplace-section marketplace-section--home">
    <div class="container app-container">
        <div class="section-heading-row section-heading-row--compact">
            <div><span class="eyebrow">Latest inventory</span><h2>Fresh on the marketplace</h2></div>
            <a href="<?= e(url('/properties')) ?>">View all properties</a>
        </div>
        <div class="inventory-grid">
            <?php foreach ($latestListings as $listing): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $listing]) ?><?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($latestProjects !== [] || $featuredCompanies !== []): ?>
<section class="marketplace-section marketplace-section--home marketplace-section--soft">
    <div class="container app-container marketplace-duo">
        <?php if ($latestProjects !== []): ?>
            <div>
                <div class="section-heading-row section-heading-row--compact">
                    <div><span class="eyebrow">Developments</span><h2>Published projects</h2></div>
                    <a href="<?= e(url('/projects')) ?>">View projects</a>
                </div>
                <div class="inventory-grid inventory-grid--projects">
                    <?php foreach ($latestProjects as $project): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'project', 'item' => $project]) ?><?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($featuredCompanies !== []): ?>
            <div>
                <div class="section-heading-row section-heading-row--compact">
                    <div><span class="eyebrow">Companies</span><h2>Approved professionals</h2></div>
                    <a href="<?= e(url('/company')) ?>">View directory</a>
                </div>
                <div class="inventory-grid inventory-grid--companies">
                    <?php foreach ($featuredCompanies as $company): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'company', 'item' => $company]) ?><?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
