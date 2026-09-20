<section class="hero-section marketplace-home-hero">
    <div class="container app-container">
        <div class="hero-grid">
            <div>
                <span class="eyebrow">Barama Real Estate</span>
                <h1>Find property, developments, and trusted real estate companies.</h1>
                <p class="hero-copy">Search approved marketplace inventory for sale and rent, discover new developments, and contact verified real estate companies through one organized platform.</p>
                <form class="home-search" action="<?= e(url('/properties')) ?>" method="get">
                    <label><span class="visually-hidden">Search properties</span><input name="q" placeholder="Search by property, developer, or location"></label>
                    <select name="offer" aria-label="Offer type"><option value="">Sale & rent</option><option value="sale">For sale</option><option value="rent">For rent</option></select>
                    <select name="location" aria-label="Location"><option value="">All locations</option><?php foreach (array_slice($locations, 0, 100) as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-primary" type="submit">Search properties</button>
                </form>
                <div class="hero-actions"><a class="btn btn-outline-secondary" href="<?= e(url('/projects')) ?>">Explore projects</a><a class="btn btn-outline-secondary" href="<?= e(url('/company')) ?>">Browse developers</a></div>
            </div>
            <aside class="foundation-panel marketplace-home-panel">
                <div class="foundation-panel__top"><span class="status-dot" aria-hidden="true"></span><span>Marketplace live foundation</span></div>
                <dl class="foundation-list"><div><dt>Properties</dt><dd>Approved listings only</dd></div><div><dt>Projects</dt><dd>Published developments</dd></div><div><dt>Companies</dt><dd>Approved profiles</dd></div><div><dt>Inquiries</dt><dd>Protected lead capture</dd></div></dl>
            </aside>
        </div>
    </div>
</section>

<?php if ($latestListings !== []): ?>
<section class="marketplace-section marketplace-section--home"><div class="container app-container"><div class="section-heading-row"><div><span class="eyebrow">Latest inventory</span><h2>Properties on the marketplace</h2><p>Recently published sale and rental listings.</p></div><a href="<?= e(url('/properties')) ?>">View all properties</a></div><div class="inventory-grid"><?php foreach ($latestListings as $listing): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $listing]) ?><?php endforeach; ?></div></div></section>
<?php endif; ?>

<?php if ($latestProjects !== []): ?>
<section class="marketplace-section marketplace-section--home"><div class="container app-container"><div class="section-heading-row"><div><span class="eyebrow">New developments</span><h2>Explore published projects</h2></div><a href="<?= e(url('/projects')) ?>">View all projects</a></div><div class="inventory-grid inventory-grid--projects"><?php foreach ($latestProjects as $project): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'project', 'item' => $project]) ?><?php endforeach; ?></div></div></section>
<?php endif; ?>

<?php if ($featuredCompanies !== []): ?>
<section class="marketplace-section marketplace-section--home"><div class="container app-container"><div class="section-heading-row"><div><span class="eyebrow">Approved companies</span><h2>Meet marketplace developers and agencies</h2></div><a href="<?= e(url('/company')) ?>">Developer directory</a></div><div class="inventory-grid inventory-grid--companies"><?php foreach ($featuredCompanies as $company): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'company', 'item' => $company]) ?><?php endforeach; ?></div></div></section>
<?php endif; ?>
