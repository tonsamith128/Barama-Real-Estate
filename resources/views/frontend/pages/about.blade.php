<x-layouts.frontend title="About — Barama Real Estate">
    <main class="marketplace info-page" id="main-content">
        <p class="marketplace__eyebrow">Our marketplace</p>
        <h1>About Barama Real Estate</h1>
        <p class="info-page__lead">A platform for discovering properties and developments and connecting with real estate companies.</p>
        <div class="info-grid">
            <article><h2>Find a property</h2><p>Browse published properties for sale or rent, review details and contact companies directly through the platform.</p>
                <a href="{{ route('properties.index') }}">Explore properties</a></article>
            <article><h2>Explore developments</h2><p>Review projects from companies whose profiles have been approved for public display.</p>
                <a href="{{ route('projects.index') }}">Browse projects</a></article>
            <article><h2>Connect with companies</h2><p>Find approved companies and send inquiries about suitable properties.</p>
                <a href="{{ route('companies.index') }}">Find companies</a></article>
        </div>
        <section class="info-page__note"><h2>How listings work</h2>
            <p>Companies submit draft listings for administrator review. Only approved and published listings appear in public search. Information about properties is provided by participating companies; confirm important facts before making any purchase or rental decision.</p>
        </section>
    </main>
</x-layouts.frontend>
