<x-layouts.account title="Saved Searches — Barama Real Estate">
    <header class="customer-heading customer-page-heading">
        <div><p class="customer-eyebrow">Your property journey</p><h1>Saved searches</h1>
            <p>Keep your favorite property filters close and revisit matching homes whenever you like.</p></div>
        <a class="button" href="{{ route('properties.index') }}">Explore properties</a>
    </header>
    <section class="customer-search-intro" aria-label="Search collection">
        <div><span class="customer-search-intro__eyebrow">YOUR PERSONAL COLLECTION</span>
            <h2>{{ $savedSearches->total() }} saved {{ \Illuminate\Support\Str::plural('search', $savedSearches->total()) }}</h2>
            <p>Save up to 20 searches. Your filters remain private and always show the latest approved listings.</p></div>
        <span class="customer-search-intro__icon" aria-hidden="true">⌕</span>
    </section>
    <div class="customer-search-grid">
        @forelse($savedSearches as $search)
            <article class="customer-search-card">
                <div class="customer-search-card__top">
                    <span class="customer-search-card__symbol" aria-hidden="true">⌕</span>
                    <span class="customer-search-card__date">Saved {{ $search->created_at->format('M j, Y') }}</span>
                </div>
                <h2>{{ $search->name }}</h2>
                <div class="customer-search-card__tags">
                    @if(!empty($search->filters['q']))<span>{{ $search->filters['q'] }}</span>@endif
                    @if(!empty($search->filters['offer']))<span>For {{ $search->filters['offer'] }}</span>@endif
                    @if(!empty($search->filters['type']))<span>{{ ucfirst(str_replace('_',' ',$search->filters['type'])) }}</span>@endif
                    @if(isset($search->filters['min_price']))<span>Minimum USD {{ number_format((float)$search->filters['min_price']) }}</span>@endif
                    @if(isset($search->filters['max_price']))<span>Maximum USD {{ number_format((float)$search->filters['max_price']) }}</span>@endif
                    @if(empty($search->filters))<span>All available properties</span>@endif
                </div>
                <div class="customer-search-card__footer">
                    <a class="button" href="{{ route('properties.index', $search->filters) }}">View matches</a>
                    <details><summary>Manage</summary>
                        <form method="post" action="{{ route('account.searches.rename', $search) }}" class="customer-search-card__rename">
                            @csrf @method('PATCH')
                            <label>New name <input name="name" value="{{ $search->name }}" maxlength="70" minlength="3" required></label>
                            <button type="submit">Rename</button>
                        </form>
                        <form method="post" action="{{ route('account.searches.destroy', $search) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="customer-danger-button">Remove saved search</button>
                        </form>
                    </details>
                </div>
            </article>
        @empty
            <section class="customer-search-empty">
                <div class="customer-search-empty__icon" aria-hidden="true">⌕</div>
                <h2>Your next favorite place starts with a search.</h2>
                <p>Browse properties, set your preferred filters, and save them to find matching homes again.</p>
                <a class="button" href="{{ route('properties.index') }}">Explore properties</a>
            </section>
        @endforelse
    </div>
    {{ $savedSearches->links() }}
</x-layouts.account>
