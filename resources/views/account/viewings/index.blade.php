<x-layouts.account title="Viewing Requests — Barama Real Estate">
    <header class="customer-heading"><p class="customer-eyebrow">Visits</p><h1>Viewing requests</h1>
        <p>A request is <strong>not a confirmed appointment</strong>. The property company must confirm availability.</p></header>
    <section class="customer-card">
        @forelse($viewings as $viewing)
            <div class="customer-record customer-record--full">
                <div><strong>{{ $viewing->listing?->property?->title ?? 'Property' }}</strong>
                    <p>Requested for {{ $viewing->requested_at->format('M j, Y \a\t g:i A') }}</p>
                    @if($viewing->message)<p>{{ $viewing->message }}</p>@endif</div>
                <div><span class="customer-status">{{ ucfirst($viewing->status) }}</span>
                    @if($viewing->status === 'pending')
                        <form method="post" action="{{ route('account.viewings.cancel', $viewing) }}">@csrf @method('PATCH')
                            <button class="button button--secondary" type="submit">Cancel request</button>
                        </form>
                    @endif</div>
            </div>
        @empty<p class="customer-empty">No viewing requests yet. Open a published property to request a visit.</p>@endforelse
        {{ $viewings->links() }}
    </section>
</x-layouts.account>
