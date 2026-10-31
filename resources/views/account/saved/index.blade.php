<x-layouts.account title="Saved Properties — Barama Real Estate">
    <header class="customer-heading"><p class="customer-eyebrow">Your shortlist</p><h1>Saved properties</h1>
        <p>Only approved and currently published listings appear in your shortlist.</p></header>
    <div class="customer-gallery">
    @forelse($saved as $item)
        <article class="customer-card">
            <h2><a href="{{ route('properties.show', $item->listing->slug) }}">{{ $item->listing->property->title }}</a></h2>
            <p>{{ $item->listing->property->company->name }} · {{ $item->listing->currency }} {{ number_format((float)$item->listing->price, 2) }}</p>
            <form method="post" action="{{ route('account.saved.destroy', $item->listing) }}">@csrf @method('DELETE')
                <button class="button button--secondary" type="submit">Remove saved property</button></form>
        </article>
    @empty
        <section class="customer-card"><h2>Nothing saved yet</h2><p>Browse published properties and save your favorites.</p>
            <a class="button" href="{{ route('properties.index') }}">Browse properties</a></section>
    @endforelse
    </div>{{ $saved->links() }}
</x-layouts.account>
