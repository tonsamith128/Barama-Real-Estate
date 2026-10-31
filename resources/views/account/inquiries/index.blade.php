<x-layouts.account title="My Inquiries — Barama Real Estate">
    <header class="customer-heading"><p class="customer-eyebrow">Communication</p><h1>My inquiries</h1>
        <p>Review your submitted property inquiries and their current status.</p></header>
    <section class="customer-card">
        @forelse($inquiries as $inquiry)
        <div class="customer-record customer-record--full">
            <div><strong>{{ $inquiry->listing?->property?->title ?? 'Property inquiry' }}</strong>
                <p>{{ \Illuminate\Support\Str::limit($inquiry->message, 180) }}</p></div>
            <small>{{ ucfirst($inquiry->status) }} · {{ $inquiry->created_at->format('M j, Y') }}</small>
        </div>
        @empty<p class="customer-empty">No inquiries yet. Find a property and use its contact form.</p>@endforelse
        {{ $inquiries->links() }}
    </section>
</x-layouts.account>
