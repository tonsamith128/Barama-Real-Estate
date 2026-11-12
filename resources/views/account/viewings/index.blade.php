<x-layouts.account title="Viewing Requests — Barama Real Estate">
    <header class="customer-heading customer-page-heading">
        <div>
            <p class="customer-eyebrow">Your property journey</p>
            <h1>Viewing requests</h1>
            <p>Appointments are not confirmed until a company approves the requested time,
                or you accept a proposed alternative.</p>
        </div>
        <a class="button" href="{{ route('properties.index') }}">Browse properties</a>
    </header>
    <section class="customer-card">
        <p>Calendar files are one-time downloads. Changes or cancellations are not automatically synchronized; download again after an update.</p>
        @forelse($viewings as $viewing)
            <div class="customer-record customer-record--full">
                <div>
                    <strong>{{ $viewing->listing?->property?->title ?? 'Property' }}</strong>
                    @if($viewing->legacy_uuid)
                        <p>Historical request · {{ $viewing->legacy_preferred_date?->format('M j, Y') ?? 'Date unavailable' }}
                            @if($viewing->legacy_preferred_window) · {{ $viewing->legacy_preferred_window }} @endif</p>
                        <p>Original status: {{ ucfirst(str_replace('_', ' ', $viewing->legacy_original_status ?: 'unknown')) }}.
                            Archived requests require independent reconfirmation; no appointment is automatically activated.</p>
                    @else
                        <p>Requested for {{ $viewing->requested_at->format('M j, Y \a\t g:i A') }}</p>
                        @if($viewing->status === 'reschedule_proposed')
                            <p>New proposed time:
                                <strong>{{ $viewing->proposed_at?->format('M j, Y g:i A') }}</strong></p>
                            <p>Respond before {{ $viewing->proposed_expires_at?->format('M j, Y g:i A') }}.
                                The new time is not confirmed until you accept it.</p>
                            @if($viewing->reschedule_note)<p>{{ $viewing->reschedule_note }}</p>@endif
                        @endif
                    @endif
                    @if($viewing->message)<p>{{ $viewing->message }}</p>@endif
                    @if($viewing->events->isNotEmpty())
                        <details>
                            <summary>Request history ({{ $viewing->events->count() }})</summary>
                            <ul>
                                @foreach($viewing->events as $event)
                                    <li>{{ $event->created_at->format('M j, Y g:i A') }}
                                        · {{ ucfirst(str_replace('_',' ',$event->event_type)) }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
                <div>
                    <span class="customer-status">{{ $viewing->legacy_uuid
                        ? 'Needs review' : ucfirst(str_replace('_',' ',$viewing->status)) }}</span>
                    @if(in_array((int) $viewing->id, $calendarExportableIds, true))
                        <a class="button button--secondary" href="{{ route('account.viewings.calendar', $viewing) }}">Download calendar (.ics)</a>
                    @endif
                    @if($viewing->review_status === 'held')
                        <p>Administration is reviewing this request. You may still cancel it.</p>
                    @endif
                    @if(!$viewing->legacy_uuid && $viewing->status === 'reschedule_proposed'
                        && $viewing->review_status !== 'held')
                        @if($viewing->proposed_expires_at?->isFuture() && $viewing->proposed_at?->isFuture())
                            <form method="post" action="{{ route('account.viewings.respond', $viewing) }}">
                                @csrf
                                <input type="hidden" name="decision" value="accept">
                                <button class="button" type="submit">Accept proposed time</button>
                            </form>
                        @else
                            <p>The proposal expired. Contact the company or cancel this request.</p>
                        @endif
                        <form method="post" action="{{ route('account.viewings.respond', $viewing) }}">
                            @csrf
                            <input type="hidden" name="decision" value="decline">
                            <button class="button button--secondary" type="submit">Decline new time</button>
                        </form>
                    @endif
                    @if(!$viewing->legacy_uuid && in_array($viewing->status, [
                        'pending','confirmed','reschedule_proposed'],true))
                        <form method="post" action="{{ route('account.viewings.cancel', $viewing) }}">
                            @csrf @method('PATCH')
                            <button class="button button--secondary" type="submit">Cancel request</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="customer-empty">No viewing requests yet. Open an approved property to request a visit.</p>
        @endforelse
        {{ $viewings->links() }}
    </section>
</x-layouts.account>
