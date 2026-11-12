<x-layouts.admin title="Viewing Requests — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Administration / Customer Relations</p>
            <h1>Viewing Requests</h1>
            <p>Review customer viewing approvals, proposed times, cancellations and immutable decision history.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="admin-card">
        <nav class="viewing-tabs" aria-label="Filter viewing requests">
            @foreach(['pending'=>'Pending','reschedule_proposed'=>'Waiting for customer','confirmed'=>'Confirmed','declined'=>'Declined','cancelled'=>'Cancelled','legacy_review'=>'Historical','all'=>'All'] as $value=>$label)
                <a @class(['is-current'=>$status===$value]) href="{{ route('admin.viewings.index', ['status'=>$value,'review'=>$review]) }}">{{ $label }}</a>
            @endforeach
        </nav>
        <nav class="viewing-tabs" aria-label="Administrative review holds">
            @foreach(['all'=>'All reviews','held'=>'On hold','clear'=>'No hold'] as $value=>$label)
                <a @class(['is-current'=>$review===$value]) href="{{ route('admin.viewings.index', ['status'=>$status,'review'=>$value]) }}">{{ $label }}</a>
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
                            <p>Historical: {{ $viewing->legacy_preferred_date?->format('M j, Y') ?? 'Date unavailable' }}
                                · {{ $viewing->legacy_preferred_window }} ·
                                Original {{ ucfirst($viewing->legacy_original_status ?: 'unknown') }}</p>
                            <p>Historical data must be independently reconciled; it cannot be confirmed here.</p>
                        @else
                            <p>Requested: {{ $viewing->requested_at->format('M j, Y g:i A') }}</p>
                            @if($viewing->proposed_at)
                                <p>Customer response needed for
                                    {{ $viewing->proposed_at->format('M j, Y g:i A') }}.
                                    Deadline {{ $viewing->proposed_expires_at?->format('M j, Y g:i A') }}.</p>
                            @endif
                        @endif
                        <p>Status: {{ ucfirst(str_replace('_',' ',$viewing->status)) }}
                            · Review: {{ $viewing->review_status === 'held' ? 'On hold' : 'No hold' }}</p>
                    </div>
                    <div class="viewing-actions">
                        @if(!$viewing->legacy_uuid && $viewing->review_status !== 'held')
                            @if($viewing->status==='pending')
                                @foreach(['confirmed'=>'Confirm','declined'=>'Decline'] as $decision=>$label)
                                    <form method="post" action="{{ route('admin.viewings.decide', $viewing) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="{{ $decision }}">
                                        <button type="submit">{{ $label }}</button>
                                    </form>
                                @endforeach
                            @endif
                            @if(in_array($viewing->status, ['pending','confirmed'],true))
                                <details>
                                    <summary>Propose new time</summary>
                                    <form method="post" action="{{ route('admin.viewings.propose', $viewing) }}">
                                        @csrf
                                        <label>Proposed time <input type="datetime-local" name="proposed_at" required></label>
                                        <label>Explanation <textarea name="reason" maxlength="1000" rows="2"></textarea></label>
                                        <button type="submit">Request customer confirmation</button>
                                    </form>
                                </details>
                            @endif
                            @if(in_array($viewing->status, ['pending','confirmed','reschedule_proposed'],true))
                                <form method="post" action="{{ route('admin.viewings.cancel', $viewing) }}">
                                    @csrf <button type="submit">Cancel</button>
                                </form>
                            @endif
                        @endif
                        <details>
                            <summary>{{ $viewing->review_status === 'held' ? 'Release review hold' : 'Place on review hold' }}</summary>
                            <form method="post" action="{{ route('admin.viewings.review', $viewing) }}">
                                @csrf
                                <input type="hidden" name="decision" value="{{ $viewing->review_status === 'held' ? 'release' : 'hold' }}">
                                <label>Internal review reason
                                    <textarea name="reason" maxlength="1000" minlength="3" rows="2" required></textarea>
                                </label>
                                <button type="submit">{{ $viewing->review_status === 'held' ? 'Release hold' : 'Hold decisions' }}</button>
                            </form>
                        </details>
                    </div>
                    @if($viewing->events->isNotEmpty())
                        <details>
                            <summary>Audit and decision history ({{ $viewing->events->count() }})</summary>
                            <ul>
                                @foreach($viewing->events as $event)
                                    <li>{{ $event->created_at->format('M j, Y g:i A') }}
                                        · {{ ucfirst(str_replace('_',' ',$event->event_type)) }}
                                        · {{ $event->actor?->name ?? 'Unavailable actor' }}
                                        @if($event->reason) · {{ $event->reason }} @endif</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </article>
            @empty
                <p>No viewing requests match the selected filters.</p>
            @endforelse
        </div>
        {{ $viewings->links() }}
    </section>
</x-layouts.admin>
