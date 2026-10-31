<x-layouts.admin title="Company Viewings — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Developer Workspace / Customer Relations</p><h1>Property Viewing Requests</h1>
            <p>Only requests for companies you actively manage are displayed here.</p></div>
        <a href="{{ route('developer.dashboard') }}">Developer Dashboard</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="admin-card">
        <div class="viewing-list">
            @forelse($viewings as $viewing)
                <article class="viewing-card">
                    <div>
                        <strong>{{ $viewing->listing?->property?->title ?? 'Archived property' }}</strong>
                        <p>{{ $viewing->listing?->property?->company?->name ?? 'Company unavailable' }}</p>
                        <p>Customer: {{ $viewing->user?->name ?? 'Account unavailable' }}</p>
                        <p>Requested: {{ $viewing->requested_at->format('M j, Y g:i A') }}</p>
                        @if($viewing->message)<p>{{ $viewing->message }}</p>@endif
                        <small>Status: {{ ucfirst($viewing->status) }} · {{ $viewing->created_at->diffForHumans() }}</small>
                    </div>
                    @if($viewing->status === 'pending')
                        <div class="viewing-actions">
                            <form method="post" action="{{ route('developer.viewings.decide', $viewing) }}">@csrf
                                <input type="hidden" name="decision" value="confirmed">
                                <button type="submit">Confirm viewing</button>
                            </form>
                            <form method="post" action="{{ route('developer.viewings.decide', $viewing) }}">@csrf
                                <input type="hidden" name="decision" value="declined">
                                <button type="submit">Decline</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty<p>No viewing requests for your companies yet.</p>@endforelse
        </div>
        {{ $viewings->links() }}
    </section>
</x-layouts.admin>
