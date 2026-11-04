<x-layouts.admin title="Customer Inquiries — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">ADMINISTRATION / CUSTOMER RELATIONSHIPS</p>
            <h1>Customer Inquiries</h1>
            <p>Review company leads, handle customer requests and open secure inquiry conversations.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}">Platform overview</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-notice" role="alert">{{ $errors->first() }}</div>@endif
    <section class="admin-card conversation-inbox" aria-labelledby="admin-inbox-heading">
        <div class="workspace-dashboard-section-heading">
            <div><p class="admin-eyebrow">CUSTOMER SUPPORT</p><h2 id="admin-inbox-heading">Inquiry inbox</h2></div>
            <span>{{ number_format($inquiries->total()) }} inquiries</span>
        </div>
        <div class="conversation-list">
            @forelse($inquiries as $inquiry)
                <article class="conversation-list-item">
                    <div>
                        <h3>{{ $inquiry->listing?->property?->title ?? 'Property unavailable' }}</h3>
                        <p>{{ $inquiry->listing?->property?->company?->name ?? 'Company unavailable' }} ·
                            {{ $inquiry->full_name }} · {{ $inquiry->email }}
                            @if($inquiry->phone) · {{ $inquiry->phone }} @endif</p>
                        <p>{{ \Illuminate\Support\Str::limit($inquiry->message, 220) }}</p>
                        <small>{{ $inquiry->created_at->format('M j, Y · g:i A') }} · {{ ucfirst($inquiry->status) }}</small>
                    </div>
                    <div class="conversation-list-item__actions">
                        <a href="{{ route('admin.inquiries.show', $inquiry) }}">Open conversation</a>
                        @if($inquiry->status === 'new')
                            <form method="post" action="{{ route('admin.inquiries.respond', $inquiry) }}">
                                @csrf @method('PATCH')
                                <button type="submit">Mark handled</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="dashboard-note">No inquiries received yet.</p>
            @endforelse
        </div>
        {{ $inquiries->links() }}
    </section>
</x-layouts.admin>
