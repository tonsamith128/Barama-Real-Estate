<x-layouts.admin title="Payment Confirmation — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Developer / Finance</p>
            <h1>Payment Confirmation</h1>
            <p>This statement is NOT a tax invoice or VAT receipt. Tax classification and issuer details await professional review.</p>
        </div>
        <a href="{{ route('developer.finance.plans.index', $company) }}">Back to billing</a>
    </header>
    <section class="admin-card">
        <h2>Confirmation {{ $invoice->invoice_number }}</h2>
        <dl class="commerce-receipt-details">
            <div><dt>Company</dt><dd>{{ $company->name }}</dd></div>
            <div><dt>Billing entity at purchase</dt><dd>{{ $invoice->customer_billing_name ?? 'Not supplied' }}</dd></div>
            <div><dt>Billing country at purchase</dt><dd>{{ $invoice->customer_country_code ?? 'Not supplied' }}</dd></div>
            <div><dt>Plan</dt><dd>{{ $order->plan_name }}</dd></div>
            <div><dt>Billing period</dt><dd>{{ $order->billing_period }}</dd></div>
            <div><dt>Amount collected</dt><dd>{{ $invoice->currency }} {{ number_format($invoice->total_cents / 100, 2) }}</dd></div>
            <div><dt>Refunded</dt><dd>{{ $invoice->currency }} {{ number_format($invoice->refunded_cents / 100, 2) }}</dd></div>
            <div><dt>Status</dt><dd>{{ $invoice->status }}</dd></div>
            <div><dt>Paid on</dt><dd>{{ $invoice->paid_at?->format('Y-m-d H:i T') }}</dd></div>
            <div><dt>Payment gateway</dt><dd>Stripe test mode</dd></div>
        </dl>
        <p>Amounts reflect a test-mode ledger. No tax rate, tax ID, jurisdiction or legal invoice status is represented.</p>
    </section>
</x-layouts.admin>
