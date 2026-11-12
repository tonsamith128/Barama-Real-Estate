<x-layouts.admin title="Company Viewings — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Developer Workspace / Customer Relations</p>
            <h1>Property Viewing Requests</h1>
            <p>Manage viewing approvals, propose alternative times and review customer responses for your approved companies.</p>
        </div>
        <div class="viewing-actions">
            <a href="{{ route('developer.viewings.calendar') }}">Export confirmed appointments (.ics)</a>
            <a href="{{ route('developer.dashboard') }}">Developer Dashboard</a>
        </div>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="admin-card">
        <nav class="viewing-tabs" aria-label="Filter company viewings">
            @foreach(['active'=>'Active', 'pending'=>'Pending', 'reschedule_proposed'=>'Waiting for customer', 'confirmed'=>'Confirmed', 'legacy_review'=>'Historical review', 'cancelled'=>'Cancelled', 'all'=>'All'] as $value=>$label)
                <a @class(['is-current'=>$status === $value]) href="{{ route('developer.viewings.index', ['status'=>$value]) }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="viewing-list">
            @forelse($viewings as $viewing)
                <article class="viewing-card">
                    <div>
                        <strong>{{ $viewing->listing?->property?->title ?? 'Archived property' }}</strong>
                        <p>{{ $viewing->listing?->property?->company?->name ?? 'Company unavailable' }}</p>
                        <p>Customer: {{ $viewing->user?->name ?? 'Account unavailable' }}</p>
                        @if($viewing->legacy_uuid)
                            <p>Historical preferred date: {{ $viewing->legacy_preferred_date?->format('M j, Y') ?? 'Unrecorded' }}
                                · {{ $viewing->legacy_preferred_window }}</p>
                            <p>Original status: {{ ucfirst($viewing->legacy_original_status ?: 'unknown') }}.
                                This historical record cannot be activated through the live booking workflow.</p>
                        @else
                            <p>Requested: {{ $viewing->requested_at->format('M j, Y g:i A') }}</p>
                            @if($viewing->proposed_at)
                                <p>Proposed: {{ $viewing->proposed_at->format('M j, Y g:i A') }} ·
                                    Expires: {{ $viewing->proposed_expires_at?->format('M j, Y g:i A') }}</p>
                            @endif
                        @endif
                        @if($viewing->message)<p>{{ $viewing->message }}</p>@endif
                        <small>Status: {{ ucfirst(str_replace('_',' ',$viewing->status)) }}
                            @if($viewing->review_status === 'held') · Administration hold @endif
                            · {{ $viewing->created_at->diffForHumans() }}</small>
                    </div>
                    @if(!$viewing->legacy_uuid && $viewing->review_status !== 'held')
                        <div class="viewing-actions">
                            @if($viewing->status === 'pending')
                                @foreach(['confirmed'=>'Confirm viewing','declined'=>'Decline'] as $value=>$label)
                                    <form method="post" action="{{ route('developer.viewings.decide', $viewing) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="{{ $value }}">
                                        <button type="submit">{{ $label }}</button>
                                    </form>
                                @endforeach
                            @endif
                            @if(in_array($viewing->status, ['pending','confirmed'], true))
                                <details>
                                    <summary>Propose different time</summary>
                                    <form method="post" action="{{ route('developer.viewings.propose', $viewing) }}">
                                        @csrf
                                        <label>Proposed date and time
                                            <input type="datetime-local" name="proposed_at" required>
                                        </label>
                                        <label>Explanation (optional)
                                            <textarea name="reason" maxlength="1000" rows="2"></textarea>
                                        </label>
                                        <button type="submit">Send proposal</button>
                                    </form>
                                </details>
                            @endif
                            @if(in_array($viewing->status, ['pending','confirmed','reschedule_proposed'], true))
                                <form method="post" action="{{ route('developer.viewings.cancel', $viewing) }}">
                                    @csrf
                                    <button type="submit">Cancel viewing</button>
                                </form>
                            @endif
                        </div>
                    @endif
                    @if($viewing->events->isNotEmpty())
                        <details>
                            <summary>Viewing history ({{ $viewing->events->count() }})</summary>
                            <ul>
                                @foreach($viewing->events as $event)
                                    <li>{{ $event->created_at->format('M j, Y g:i A') }}
                                        · {{ ucfirst(str_replace('_',' ',$event->event_type)) }}
                                        · {{ ucfirst($event->actor_kind) }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </article>
            @empty
                <p>No viewing requests match this filter for companies you manage.</p>
            @endforelse
        </div>
        {{ $viewings->links() }}
    </section>
</x-layouts.admin>
