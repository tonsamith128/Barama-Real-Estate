<section class="hero-section">
    <div class="container app-container">
        <div class="hero-grid">
            <div>
                <span class="eyebrow">Barama Real Estate</span>
                <h1>A clean foundation for a complete real estate marketplace.</h1>
                <p class="hero-copy">
                    Discover properties, trusted developers, and new developments through one
                    organized marketplace built for buyers, renters, investors, and real estate professionals.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="<?= e(url('/company')) ?>">Explore developers</a>
                    <a class="btn btn-outline-secondary btn-lg" href="<?= e(url('/developer')) ?>">Developer workspace</a>
                </div>
            </div>

            <aside class="foundation-panel" aria-label="Platform foundation">
                <div class="foundation-panel__top">
                    <span class="status-dot" aria-hidden="true"></span>
                    <span>Foundation active</span>
                </div>
                <dl class="foundation-list">
                    <div><dt>Public marketplace</dt><dd>Ready for modules</dd></div>
                    <div><dt>User account</dt><dd>/account</dd></div>
                    <div><dt>Developer portal</dt><dd>/developer</dd></div>
                    <div><dt>Admin platform</dt><dd>/dashboard</dd></div>
                </dl>
            </aside>
        </div>
    </div>
</section>

<section class="section-space">
    <div class="container app-container">
        <div class="section-heading">
            <span class="eyebrow">Architecture</span>
            <h2>Separate concerns, one canonical system.</h2>
            <p>
                Public marketplace, customer account, company profiles, developer operations,
                and administration are intentionally separated before feature development begins.
            </p>
        </div>

        <div class="feature-grid">
            <article class="feature-card">
                <span class="feature-card__number">01</span>
                <h3>Marketplace</h3>
                <p>Property discovery, project discovery, search, filters, and public content.</p>
            </article>
            <article class="feature-card">
                <span class="feature-card__number">02</span>
                <h3>Customers</h3>
                <p>Saved properties, comparisons, inquiries, viewing requests, and alerts.</p>
            </article>
            <article class="feature-card">
                <span class="feature-card__number">03</span>
                <h3>Developers</h3>
                <p>Companies, projects, listings, leads, teams, analytics, plans, and billing.</p>
            </article>
            <article class="feature-card">
                <span class="feature-card__number">04</span>
                <h3>Administration</h3>
                <p>Moderation, users, finance, content, platform settings, security, and operations.</p>
            </article>
        </div>
    </div>
</section>
