<x-layouts.frontend title="Property Companies — Barama Real Estate">
    <main class="marketplace" id="main-content">
        <header class="marketplace__header">
            <div>
                <p class="marketplace__eyebrow">Barama Real Estate</p>
                <h1>Property Companies</h1>
                <p>Explore approved and published companies.</p>
            </div>
            <a href="{{ route('home') }}">Home</a>
        </header>
        <form class="marketplace-filters" action="{{ route('companies.index') }}" method="get" role="search">
            <label class="field" for="company-search">
                <span>Company name</span>
                <input class="field__control" type="search" name="q" id="company-search"
                    maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Search approved companies">
            </label>
            <label class="field" for="company-type">
                <span>Company type</span>
                <select class="field__control" name="type" id="company-type">
                    <option value="">All company types</option>
                    @foreach ($companyTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="field" for="company-sort">
                <span>Sort by</span>
                <select class="field__control" name="sort" id="company-sort">
                    <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Name A–Z</option>
                    <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Recently published</option>
                </select>
            </label>
            <button type="submit" class="button">Find companies</button>
            <a class="button button--secondary" href="{{ route('companies.index') }}">Clear filters</a>
        </form>

        @if ($errors->any())
            <div class="notice notice--error" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <p class="marketplace__count"><strong>{{ number_format($companies->total()) }}</strong> approved companies</p>
        <div class="marketplace__grid">
            @forelse ($companies as $company)
                <article class="property-card">
                    <h2 class="property-card__title"><a href="{{ route('companies.show', $company->slug) }}">{{ $company->name }}</a></h2>
                    <p class="property-card__meta">{{ ucfirst(str_replace('_', ' ', $company->company_type)) }}</p>
                    <p class="property-card__meta">
                        {{ number_format($company->published_projects_count) }} published projects
                        &middot; {{ number_format($company->published_listings_count) }} active listings
                    </p>
                </article>
            @empty
                <p class="notice">No public companies are available yet.</p>
            @endforelse
        </div>
        {{ $companies->links() }}
    </main>
</x-layouts.frontend>
