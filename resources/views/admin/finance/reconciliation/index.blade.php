<x-layouts.admin title="Billing Reconciliation — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Administration / Finance</p>
            <h1>Billing Reconciliation</h1>
            <p>Read-only risk review. Never override Stripe payment or dispute state manually.</p>
        </div>
        <a href="{{ route('admin.finance.billing.index') }}">Billing ledger</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="admin-card">
        <h2>International commerce release gates</h2>
        <p>Live payments are <strong>not approved</strong>. A passing local test is not merchant, tax, provider or shared-hosting acceptance.</p>
        <ul class="commerce-inquiry-list">
            @foreach($readiness['external_gates'] as $name => $gate)
                <li><strong>{{ str_replace('_', ' ', ucfirst($name)) }}:</strong> {{ $gate['status'] }} — {{ $gate['required_evidence'] }}</li>
            @endforeach
        </ul>
        <p>Local configuration checks passing: {{ $readiness['local_checks_pass'] ? 'Yes' : 'No' }}. For safe console reporting, run <code>php artisan billing:release-check --json</code>.</p>
    </section>
    @if(!$summary['ready'])
        <div class="admin-errors" role="alert">
            <p>Billing migrations are missing: {{ implode(', ', $summary['missing_tables']) }}</p>
        </div>
    @else
        <section class="admin-card">
            <h2>Reconciliation queue</h2>
            <div class="commerce-ops-grid">
                @foreach([
                    'pending_checkouts'=>'Old pending checkouts',
                    'unprocessed_events'=>'Stale webhook receipts',
                    'uncertain_refunds'=>'Uncertain refund requests',
                    'unmatched_disputes'=>'Disputes requiring matching',
                    'tax_review_pending'=>'Tax review outstanding',
                    'expired_entitlements'=>'Expired active entitlements',
                ] as $key => $label)
                    <div class="commerce-ops-item"><strong>{{ $summary[$key] }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
            <p>Sandbox checkout: {{ $summary['checkout_enabled'] ? 'Enabled for testing' : 'Off' }}
                · Auto-renewal test: {{ $summary['recurring_test_enabled'] ? 'Enabled for testing' : 'Off' }}</p>
        </section>
        <section class="admin-card">
            <h2>Dispute register</h2>
            <p>Unmatched disputes must be correlated with the processor before any accounting conclusion.</p>
            <ul class="commerce-inquiry-list">
                @forelse($disputes as $dispute)
                    <li>{{ $dispute->gateway_dispute_id }} · {{ $dispute->currency }} {{ number_format($dispute->amount_cents / 100, 2) }}
                        · {{ $dispute->status }} · {{ $dispute->payment?->order?->company?->name ?? 'Unmatched payment' }}
                        @if($dispute->needs_reconciliation) · Needs provider reconciliation @endif</li>
                @empty
                    <li>No disputes recorded.</li>
                @endforelse
            </ul>
        </section>
        <section class="admin-card">
            <h2>Tax document readiness</h2>
            <p>These are recorded professional-review work items, not statutory invoices, tax calculations, tax approval or legal advice.</p>
            <ul class="commerce-inquiry-list">
                @forelse($taxReviews as $review)
                    <li>
                        <strong>{{ $review->invoice?->invoice_number }} · {{ $review->invoice?->order?->company?->name }}</strong>
                        · {{ $review->status }}
                        @if($review->invoice?->paid_at)
                            <form class="admin-form" method="post" action="{{ route('admin.finance.tax-reviews.update', $review) }}">
                                @csrf
                                @method('PATCH')
                                <label>Work-queue status
                                    <select name="status" required>
                                        @foreach(['unreviewed' => 'Unreviewed', 'needs_specialist' => 'Specialist review required', 'reviewed_nonstatutory' => 'Internal review recorded (NOT tax approval)'] as $value => $title)
                                            <option value="{{ $value }}" @selected($review->status === $value)>{{ $title }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Jurisdiction (ISO country code, optional)
                                    <select name="jurisdiction">
                                        <option value="">Unspecified</option>
                                        @foreach(config('billing_countries', []) as $code => $country)
                                            <option value="{{ $code }}" @selected($review->jurisdiction === $code)>{{ $country }} ({{ $code }})</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Professional review notes
                                    <textarea name="review_note" rows="2" required minlength="20" maxlength="1500">{{ old('review_note', $review->review_note) }}</textarea>
                                </label>
                                <button type="submit">Save review note (no tax invoice)</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li>No pending tax reviews.</li>
                @endforelse
            </ul>
        </section>
    @endif
</x-layouts.admin>
