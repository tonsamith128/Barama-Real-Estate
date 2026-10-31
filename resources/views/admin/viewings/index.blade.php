<x-layouts.admin title="Viewing Requests — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Administration / Marketplace</p>
            <h1>Viewing Requests</h1><p>Review viewing requests for all companies and notify customers of your decision.</p></div>
        <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="admin-card">
        <nav class="viewing-tabs" aria-label="Filter viewing requests">
            @foreach(['pending'=>'Pending', 'confirmed'=>'Confirmed', 'declined'=>'Declined', 'cancelled'=>'Cancelled', 'all'=>'All'] as $value => $label)
                <a @class(['is-current' => $status === $value]) href="{{ route('admin.viewings.index', ['status' => $value]) }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="viewing-list">
            @forelse($viewings as $viewing)
                <article class="viewing-card">
                    <div>
                        <strong>{{ $viewing->listing?->property?->title ?? 'Archived property' }}</strong>
                        <p>{{ $viewing->listing?->property?->company?->name ?? 'Company unavailable' }}</p>
                        <p>Customer: {{ $viewing->user?->name ?? 'Account unavailable' }}</p>
                        <p>Requested: {{ $viewing->requested_at->format('M j, Y g:i A') }}</p>
                        @if($viewing->message)<p>{{ $viewing->message }}</p>@endif
                        <small>Status: {{ ucfirst($viewing->status) }} · Received {{ $viewing->created_at->diffForHumans() }}</small>
                    </div>
                    @if($viewing->status === 'pending')
                        <div class="viewing-actions">
                            <form method="post" action="{{ route('admin.viewings.decide', $viewing) }}">@csrf
                                <input type="hidden" name="decision" value="confirmed">
                                <button type="submit">Confirm viewing</button>
                            </form>
                            <form method="post" action="{{ route('admin.viewings.decide', $viewing) }}">@csrf
                                <input type="hidden" name="decision" value="declined">
                                <button type="submit">Decline</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty<p>No viewing requests match this filter.</p>@endforelse
        </div>
        {{ $viewings->links() }}
    </section>
</x-layouts.admin>
