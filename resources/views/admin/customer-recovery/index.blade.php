<x-layouts.admin title="Customer Data Recovery — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Administration / Migration Review</p>
            <h1>Historical customer records</h1>
            <p>Private migration evidence only. Reviewing a record never emails a customer,
                activates a company role, or creates a live sales opportunity.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="admin-card">
        <nav class="viewing-tabs" aria-label="Historical customer review filters">
            @foreach(['needs_review'=>'Needs review','needs_followup'=>'Manual follow-up','reviewed'=>'Reviewed','all'=>'All'] as $value=>$label)
                <a @class(['is-current'=>$status===$value])
                    href="{{ route('admin.customer-recovery.index', ['status'=>$value]) }}">{{ $label }}</a>
            @endforeach
        </nav>
        <h2>Historical inquiries ({{ $inquiries->total() }})</h2>
        <p>Original company-only and incomplete inquiries remain private; customer consent and communication permissions must be checked independently.</p>
        <div class="viewing-list">
            @forelse($inquiries as $inquiry)
                <article class="viewing-card">
                    <div>
                        <strong>{{ $inquiry->contact_name }}</strong>
                        <p>{{ $inquiry->company?->name ?? 'No company linked' }}</p>
                        <p>{{ $inquiry->subject ?: 'General historical inquiry' }}</p>
                        <p>Original status: {{ $inquiry->source_status }} ·
                            Imported review: {{ str_replace('_',' ',$inquiry->review_status) }}</p>
                        <details>
                            <summary>View private historical inquiry</summary>
                            <p>Contact email: {{ $inquiry->contact_email ?: 'Not supplied' }}</p>
                            <p>Phone: {{ $inquiry->contact_phone ?: 'Not supplied' }}</p>
                            <p>{{ $inquiry->message }}</p>
                        </details>
                    </div>
                    <details>
                        <summary>Review inquiry</summary>
                        <form method="post" action="{{ route('admin.customer-recovery.inquiries.review', $inquiry) }}">
                            @csrf
                            <label>Review decision
                                <select name="decision" required>
                                    <option value="reviewed">Reviewed (archive only)</option>
                                    <option value="needs_followup">Needs authorized follow-up</option>
                                </select>
                            </label>
                            <label>Internal review note
                                <textarea name="note" minlength="3" maxlength="1000" rows="2" required></textarea>
                            </label>
                            <button type="submit">Record review</button>
                        </form>
                    </details>
                </article>
            @empty
                <p>No historical inquiries match this review filter.</p>
            @endforelse
        </div>
        {{ $inquiries->links() }}
    </section>
    <section class="admin-card">
        <h2>Historical leads ({{ $leads->total() }})</h2>
        <p>Original stages and follow-up notes are evidence only. Lead outreach and customer communication remain disabled.</p>
        <div class="viewing-list">
            @forelse($leads as $lead)
                <article class="viewing-card">
                    <div>
                        <strong>{{ $lead->contact_name }}</strong>
                        <p>{{ $lead->company?->name ?? 'Unavailable company' }}</p>
                        <p>Original stage: {{ $lead->source_stage }} ·
                            Review: {{ str_replace('_',' ',$lead->review_status) }}</p>
                        @if($lead->next_follow_up_at)
                            <p>Historical follow-up date: {{ $lead->next_follow_up_at->format('M j, Y') }}.
                                Do not treat this as an active appointment.</p>
                        @endif
                        <details>
                            <summary>View private lead evidence</summary>
                            <p>Contact: {{ $lead->contact_email ?: 'No email' }}
                                · {{ $lead->contact_phone ?: 'No phone' }}</p>
                            @if($lead->notes)<p>{{ $lead->notes }}</p>@endif
                        </details>
                    </div>
                    <details>
                        <summary>Review lead</summary>
                        <form method="post" action="{{ route('admin.customer-recovery.leads.review', $lead) }}">
                            @csrf
                            <label>Review decision
                                <select name="decision" required>
                                    <option value="reviewed">Reviewed (archive only)</option>
                                    <option value="needs_followup">Needs authorized follow-up</option>
                                </select>
                            </label>
                            <label>Internal review note
                                <textarea name="note" minlength="3" maxlength="1000" rows="2" required></textarea>
                            </label>
                            <button type="submit">Record review</button>
                        </form>
                    </details>
                </article>
            @empty
                <p>No historical leads match this filter.</p>
            @endforelse
        </div>
        {{ $leads->links() }}
    </section>
</x-layouts.admin>
