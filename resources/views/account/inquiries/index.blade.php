<x-layouts.account title="My Inquiries — Barama Real Estate">
    <header class="customer-heading">
        <p class="customer-eyebrow">PRIVATE COMMUNICATIONS</p>
        <h1>My inquiries</h1>
        <p>Follow property conversations, review replies and contact your property team securely.</p>
    </header>
    <section class="customer-card">
        <div class="customer-card__heading">
            <div><h2>Inquiry inbox</h2><p>Only inquiries submitted from this account appear here.</p></div>
            <a href="{{ route('properties.index') }}">Find properties</a>
        </div>
        <div class="conversation-list">
            @forelse($inquiries as $inquiry)
                <article class="conversation-list-item">
                    <div>
                        <h3>{{ $inquiry->listing?->property?->title ?? 'Property unavailable' }}</h3>
                        <p>{{ \Illuminate\Support\Str::limit($inquiry->message, 180) }}</p>
                        <small>{{ $inquiry->created_at->format('M j, Y') }} · {{ ucfirst($inquiry->status) }}</small>
                    </div>
                    <a href="{{ route('account.inquiries.show', $inquiry) }}">Open conversation</a>
                </article>
            @empty
                <p class="customer-empty">No inquiries yet. Explore properties and use a listing's contact form to start a conversation.</p>
            @endforelse
        </div>
        {{ $inquiries->links() }}
    </section>
</x-layouts.account>
