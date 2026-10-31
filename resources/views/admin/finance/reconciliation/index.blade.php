<x-layouts.admin title="Billing Reconciliation — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Administration / Finance</p>
            <h1>Billing Reconciliation</h1>
            <p>Read-only risk review. Never override Stripe payment or dispute state manually.</p>
        </div>
        <a href="{{ route('admin.finance.billing.index') }}">Billing ledger</a>
    </header>
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
            <p>These are pending professional reviews, not issued legal tax invoices.</p>
            <ul class="commerce-inquiry-list">
                @forelse($taxReviews as $review)
                    <li>{{ $review->invoice?->invoice_number }} · {{ $review->invoice?->order?->company?->name }}
                        · {{ $review->status }}</li>
                @empty
                    <li>No pending tax reviews.</li>
                @endforelse
            </ul>
        </section>
    @endif
</x-layouts.admin>
