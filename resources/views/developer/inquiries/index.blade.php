<x-layouts.admin title="Company Inquiries — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">DEVELOPER / CUSTOMER RELATIONSHIPS</p>
            <h1>Your company inquiries</h1>
            <p>Manage inquiries for approved companies where you are an active owner or manager.</p>
        </div>
        <a href="{{ route('developer.dashboard') }}">Company overview</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-notice" role="alert">{{ $errors->first() }}</div>@endif
    <section class="admin-card conversation-inbox" aria-labelledby="company-inbox-heading">
        <div class="workspace-dashboard-section-heading"><div>
            <p class="admin-eyebrow">LEAD MANAGEMENT</p><h2 id="company-inbox-heading">Customer conversations</h2>
        </div><span>{{ number_format($inquiries->total()) }} inquiries</span></div>
        <div class="conversation-list">
            @forelse($inquiries as $inquiry)
                <article class="conversation-list-item">
                    <div>
                        <h3>{{ $inquiry->listing?->property?->title ?? 'Property unavailable' }}</h3>
                        <p>{{ $inquiry->full_name }} · {{ $inquiry->email }}
                            · {{ $inquiry->listing?->property?->company?->name ?? 'Company unavailable' }}</p>
                        <p>{{ \Illuminate\Support\Str::limit($inquiry->message, 220) }}</p>
                        <small>{{ $inquiry->created_at->format('M j, Y · g:i A') }} · {{ ucfirst($inquiry->status) }}</small>
                    </div>
                    <div class="conversation-list-item__actions">
                        <a href="{{ route('developer.inquiries.show', $inquiry) }}">Open conversation</a>
                        @if($inquiry->status === 'new')
                            <form method="post" action="{{ route('developer.inquiries.respond', $inquiry) }}">
                                @csrf @method('PATCH')
                                <button type="submit">Mark handled</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="dashboard-note">No inquiries yet for the companies you manage.</p>
            @endforelse
        </div>
        {{ $inquiries->links() }}
    </section>
</x-layouts.admin>
