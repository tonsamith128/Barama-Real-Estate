<x-layouts.frontend title="Barama Real Estate — Find Properties and Developments">
    <main id="main-content">
        <section class="home-hero">
            <div class="home-hero__inner">
                <p class="home-eyebrow">BARAMA REAL ESTATE</p>
                <h1>Find your next place to call home.</h1>
                <p>Explore properties for sale and rent, discover developments, and connect with real estate companies.</p>
                <form method="get" action="{{ route('properties.index') }}" role="search" class="home-search">
                    <label for="home-keywords">Search properties</label>
                    <input id="home-keywords" name="q" type="search" placeholder="Search by name or reference" maxlength="120">
                    <button class="button" type="submit">Search properties</button>
                </form>
                <nav class="home-hero__links" aria-label="Marketplace">
                    <a href="{{ route('properties.index') }}">Properties</a>
                    <a href="{{ route('projects.index') }}">Projects</a>
                    <a href="{{ route('companies.index') }}">Companies</a>
                    @auth
                        @if(auth()->user()->isSystemAdministrator())
                            <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
                        @else
                            <a href="{{ route('account.dashboard') }}">My account</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}">Sign in</a>
                    @endauth
                </nav>
            </div>
        </section>
        <section class="home-content" aria-label="Marketplace overview">
            <div class="home-metrics">
                <div><strong>{{ number_format($propertyCount) }}</strong><span>Published listings</span></div>
                <div><strong>{{ number_format($projectCount) }}</strong><span>Developments</span></div>
                <div><strong>{{ number_format($companyCount) }}</strong><span>Approved companies</span></div>
            </div>
            <div class="home-section-heading">
                <div><p class="home-eyebrow">DISCOVER</p><h2>Recently listed properties</h2></div>
                <a href="{{ route('properties.index') }}">View all properties</a>
            </div>
            @if($listings->isNotEmpty())
                <div class="marketplace__grid">
                    @foreach($listings as $listing)
                        <x-marketplace.listing-card :listing="$listing" />
                    @endforeach
                </div>
            @else
                <div class="home-empty">
                    <h3>We're preparing the property catalog.</h3>
                    <p>There are no published listings yet. Once approved properties are added, they will appear here automatically.</p>
                    <div class="actions">
                        <a class="button button--secondary" href="{{ route('projects.index') }}">Explore projects</a>
                        <a class="button button--secondary" href="{{ route('companies.index') }}">Explore companies</a>
                        @auth
                            @if(auth()->user()->isSystemAdministrator())
                                <a class="button" href="{{ route('admin.catalog.index') }}">Manage listings</a>
                                <a class="button button--secondary" href="{{ route('admin.demo.index') }}">Demo importer</a>
                            @endif
                        @endauth
                    </div>
                </div>
            @endif
        </section>
        <footer class="home-footer">
            <span>© {{ date('Y') }} Barama Real Estate</span>
            <a href="{{ route('legal.index') }}">Legal policies</a>
            <a href="{{ route('home') }}">Back to top</a>
        </footer>
    </main>
</x-layouts.frontend>
