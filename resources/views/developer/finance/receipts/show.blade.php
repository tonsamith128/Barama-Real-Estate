<x-layouts.admin title="Payment Confirmation — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Developer / Finance</p>
            <h1>{{ __('billing.receipt', [], $billingLocale) }}</h1>
            <p>{{ __('billing.receipt_intro', [], $billingLocale) }} This statement is NOT a tax invoice or VAT receipt. Tax classification and issuer details await professional review.</p>
        </div>
        <a href="{{ route('developer.finance.plans.index', $company) }}">Back to billing</a>
    </header>
    <section class="admin-card">
        <h2>{{ __('billing.invoice', [], $billingLocale) }} {{ $invoice->invoice_number }}</h2>
        <dl class="commerce-receipt-details">
            <div><dt>{{ __('billing.company', [], $billingLocale) }}</dt><dd>{{ $company->name }}</dd></div>
            <div><dt>{{ __('billing.identity', [], $billingLocale) }}</dt><dd>{{ $invoice->customer_billing_name ?? __('billing.not_supplied', [], $billingLocale) }}</dd></div>
            <div><dt>{{ __('billing.billing_country', [], $billingLocale) }}</dt><dd>{{ $invoice->customer_country_code ?? __('billing.not_supplied', [], $billingLocale) }}</dd></div>
            <div><dt>{{ __('billing.plan', [], $billingLocale) }}</dt><dd>{{ $order->plan_name }}</dd></div>
            <div><dt>{{ __('billing.period', [], $billingLocale) }}</dt><dd>{{ $order->billing_period }}</dd></div>
            <div><dt>{{ __('billing.amount', [], $billingLocale) }}</dt><dd>{{ $invoice->currency }} {{ number_format($invoice->total_cents / 100, 2) }}</dd></div>
            <div><dt>{{ __('billing.refunded', [], $billingLocale) }}</dt><dd>{{ $invoice->currency }} {{ number_format($invoice->refunded_cents / 100, 2) }}</dd></div>
            <div><dt>{{ __('billing.status', [], $billingLocale) }}</dt><dd>{{ $invoice->status }}</dd></div>
            <div><dt>{{ __('billing.paid_on', [], $billingLocale) }}</dt><dd>{{ $invoice->paid_at?->format('Y-m-d H:i T') }}</dd></div>
            <div><dt>{{ __('billing.gateway', [], $billingLocale) }}</dt><dd>Stripe test mode</dd></div>
        </dl>
        <p>{{ __('billing.receipt_note', [], $billingLocale) }}</p>
        <p>Amounts reflect a test-mode ledger. No tax rate, tax ID, jurisdiction or legal invoice status is represented.</p>
    </section>
</x-layouts.admin>
