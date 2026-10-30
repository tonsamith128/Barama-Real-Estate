<x-layouts.frontend title="Properties for Sale & Rent — Barama Real Estate">
    <main class="marketplace" id="main-content">
        <header class="marketplace__header">
            <div>
                <p class="marketplace__eyebrow">Barama Real Estate</p>
                <h1>Properties for Sale & Rent</h1>
                <p>Browse approved, published property listings.</p>
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
            <button class="button" type="submit">Apply filters</button>
        </form>

        @if ($errors->any())
            <p class="notice" role="alert">Please review the search filters.</p>
        @endif

        <p class="marketplace__count">{{ $listings->total() }} available listings</p>

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
