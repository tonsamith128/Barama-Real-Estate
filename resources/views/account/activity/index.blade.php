<x-layouts.account title="Compare & Recently Viewed — Barama Real Estate">
    <header class="customer-heading customer-page-heading">
        <div>
            <p class="customer-eyebrow">Your property journey</p>
            <h1>Compare &amp; recently viewed</h1>
            <p>Keep a private shortlist of up to five approved property offers.
                Recent history is only recorded when you choose to remember a property.</p>
        </div>
        <a class="button" href="{{ route('properties.index') }}">Browse properties</a>
    </header>

    <section class="customer-card" aria-labelledby="comparison-heading">
        <h2 id="comparison-heading">Your comparison shortlist ({{ $comparison->count() }}/5)</h2>
        @forelse($comparison as $item)
            <div class="customer-record customer-record--full">
                <div>
                    <strong>{{ $item->listing->property->title }}</strong>
                    <p>{{ $item->listing->property->company->name }} ·
                        {{ ucfirst($item->listing->transaction_type) }} ·
                        {{ $item->listing->currency }} {{ number_format((float) $item->listing->price, 2) }}</p>
                    <a href="{{ route('properties.show', $item->listing->slug) }}">View approved offer</a>
                </div>
                <form method="post" action="{{ route('account.activity.comparison.destroy', $item->listing) }}">
                    @csrf @method('DELETE')
                    <button class="button button--secondary" type="submit">Remove</button>
                </form>
            </div>
        @empty
            <p class="customer-empty">No offers in your comparison list yet. Open a published property and choose Compare.</p>
        @endforelse
    </section>

    <section class="customer-card" aria-labelledby="recent-heading">
        <div class="customer-page-heading">
            <div><h2 id="recent-heading">Recently remembered properties</h2>
                <p>This is your private opt-in property history, not a record of every page visit.</p></div>
            @if($recent->total() > 0)
                <form method="post" action="{{ route('account.activity.recent.clear') }}">
                    @csrf @method('DELETE')
                    <button class="button button--secondary" type="submit">Clear recent history</button>
                </form>
            @endif
        </div>
        @forelse($recent as $item)
            <div class="customer-record customer-record--full">
                <div>
                    <strong>{{ $item->listing->property->title }}</strong>
                    <p>{{ $item->listing->property->company->name }} ·
                        Last remembered {{ $item->last_viewed_at->format('M j, Y') }}</p>
                    <a href="{{ route('properties.show', $item->listing->slug) }}">View approved offer</a>
                </div>
                <form method="post" action="{{ route('account.activity.recent.destroy', $item->listing) }}">
                    @csrf @method('DELETE')
                    <button class="button button--secondary" type="submit">Forget</button>
                </form>
            </div>
        @empty
            <p class="customer-empty">You have not chosen to remember any published properties.</p>
        @endforelse
        {{ $recent->links() }}
    </section>
</x-layouts.account>
