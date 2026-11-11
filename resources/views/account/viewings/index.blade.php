<x-layouts.account title="Viewing Requests — Barama Real Estate">
    <header class="customer-heading"><p class="customer-eyebrow">Visits</p><h1>Viewing requests</h1>
        <p>A request is <strong>not a confirmed appointment</strong>. The property company must confirm availability.</p></header>
    <section class="customer-card">
        @forelse($viewings as $viewing)
            <div class="customer-record customer-record--full">
                <div><strong>{{ $viewing->listing?->property?->title ?? 'Property' }}</strong>
                    @if($viewing->legacy_uuid)
                        <p>Historical request · {{ $viewing->legacy_preferred_date?->format('M j, Y') ?? 'Date unavailable' }}
                            @if($viewing->legacy_preferred_window) · {{ $viewing->legacy_preferred_window }} @endif</p>
                        <p>Original status: {{ ucfirst(str_replace('_', ' ', $viewing->legacy_original_status ?: 'unknown')) }}.
                            Archived requests require independent reconfirmation; no appointment is automatically activated.</p>
                    @else
                        <p>Requested for {{ $viewing->requested_at->format('M j, Y \a\t g:i A') }}</p>
                    @endif
                    @if($viewing->message)<p>{{ $viewing->message }}</p>@endif</div>
                <div><span class="customer-status">{{ $viewing->legacy_uuid ? 'Needs review' : ucfirst($viewing->status) }}</span>
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
