<x-layouts.frontend title="Properties for Sale & Rent — Barama Real Estate">
    <main class="marketplace" id="main-content">
        <header class="marketplace__header marketplace__header--premium">
            <div>
                <p class="marketplace__eyebrow">Barama Real Estate</p>
                <h1>Find a place that feels like yours.</h1>
                <p>Explore verified listings, refine your budget, and save the searches that matter.</p>
            </div>
            <a href="{{ route('home') }}">Home</a>
        </header>

        <form class="marketplace-filters" method="get" action="{{ route('properties.index') }}" role="search">
            <label class="field" for="property-search">
                <span>Keywords</span>
                <input class="field__control" id="property-search" name="q" type="search"
                    value="{{ $filters['q'] ?? '' }}" placeholder="Property, reference or company">
            </label>
            <label class="field" for="property-offer">
                <span>Transaction</span>
                <select class="field__control" id="property-offer" name="offer">
                    <option value="">All offers</option>
                    <option value="sale" @selected(($filters['offer'] ?? '') === 'sale')>For sale</option>
                    <option value="rent" @selected(($filters['offer'] ?? '') === 'rent')>For rent</option>
                </select>
            </label>
            <label class="field" for="property-type">
                <span>Property type</span>
                <select class="field__control" id="property-type" name="type">
                    <option value="">All types</option>
                    @foreach ($propertyTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="field" for="property-sort">
                <span>Sort by</span>
                <select class="field__control" id="property-sort" name="sort">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest</option>
                    <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price: low to high</option>
                    <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price: high to low</option>
                </select>
            </label>
            <label class="field" for="price-minimum">
                <span>Min. price (USD)</span>
                <input class="field__control" id="price-minimum" name="min_price" type="number"
                    min="0" step="0.01" inputmode="decimal" value="{{ $filters['min_price'] ?? '' }}" placeholder="Any minimum">
            </label>
            <label class="field" for="price-maximum">
                <span>Max. price (USD)</span>
                <input class="field__control" id="price-maximum" name="max_price" type="number"
                    min="0" step="0.01" inputmode="decimal" value="{{ $filters['max_price'] ?? '' }}" placeholder="Any maximum">
            </label>
            <button class="button" type="submit">Show matching properties</button>
        </form>

        @if ($errors->any())
            <p class="notice" role="alert">Please review the search filters.</p>
        @endif

        <div class="marketplace-search-tools">
            <div><span class="marketplace-search-tools__label">CURATED FOR YOUR SEARCH</span>
                <p class="marketplace__count"><strong>{{ number_format($listings->total()) }}</strong> matching listings</p></div>
            @auth
                <details class="marketplace-save-search">
                    <summary>Save this search</summary>
                    <form method="post" action="{{ route('account.searches.store') }}">
                        @csrf
                        <label for="saved-search-name">Name this search</label>
                        <input id="saved-search-name" name="name" required minlength="3" maxlength="70"
                            value="{{ old('name') }}" placeholder="e.g. Family homes under 200k">
                        @foreach(['q','offer','type','sort','min_price','max_price'] as $key)
                            @if(isset($filters[$key]) && $filters[$key] !== '')
                                <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
                            @endif
                        @endforeach
                        <button class="button" type="submit">Save to my account</button>
                        <p>Up to 20 private saved searches. You can remove them anytime.</p>
                    </form>
                </details>
            @else
                <a class="button button--secondary" href="{{ route('login') }}">Sign in to save this search</a>
            @endauth
        </div>
        @if($errors->any())
            <div class="notice notice--error" role="alert">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @forelse ($listings as $listing)
            @if ($loop->first)
                <div class="marketplace__grid">
            @endif
                <x-marketplace.listing-card :listing="$listing" />
            @if ($loop->last)
                </div>
            @endif
        @empty
            <p class="notice">No matching listings were found. Try adjusting your filters.</p>
        @endforelse

        {{ $listings->links() }}
    </main>
</x-layouts.frontend>
