<x-layouts.admin title="Billing Ledger — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Administration / Finance</p>
            <h1>Billing Ledger</h1>
            <p>Test-mode payment, invoice and refund records. Provider webhooks, not manual status edits, verify settlements.</p>
        </div>
        <a href="{{ route('admin.finance.plans.index') }}">Pricing plans</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <section class="admin-card">
        <h2>Orders and invoices</h2>
        <div class="commerce-ledger-scroll">
            <table class="commerce-ledger-table">
                <thead><tr><th>Invoice</th><th>Company</th><th>Plan</th><th>Total</th><th>Order</th><th>Invoice status</th><th>Paid at</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>{{ $order->invoice?->invoice_number ?? 'Pending' }}</td>
                        <td>{{ $order->company?->name }}</td>
                        <td>{{ $order->plan_name }}</td>
                        <td>{{ $order->currency }} {{ number_format($order->amount_cents / 100, 2) }}</td>
                        <td>{{ $order->status }}</td>
                        <td>{{ $order->invoice?->status ?? 'Unknown' }}</td>
                        <td>{{ $order->paid_at?->format('Y-m-d H:i') ?? 'Not paid' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No billing orders recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </section>

    <section class="admin-card">
        <h2>Verified payments and refund requests</h2>
        @forelse($payments as $payment)
            <div class="commerce-ledger-payment">
                <h3>{{ $payment->order?->company?->name }} · {{ $payment->currency }}
                    {{ number_format($payment->amount_cents / 100, 2) }}</h3>
                <p>Provider ID: {{ $payment->gateway_payment_id }} ·
                    {{ ucfirst(str_replace('_', ' ', $payment->status)) }} ·
                    Refunded {{ number_format($payment->refunded_cents / 100, 2) }}</p>
                @if($payment->refunds->isNotEmpty())
                    <ul>
                        @foreach($payment->refunds as $refund)
                            <li>{{ $refund->status }} · {{ number_format($refund->amount_cents / 100, 2) }} ·
                                {{ $refund->reason }}</li>
                        @endforeach
                    </ul>
                @endif
                @if(config('billing.enabled') && $payment->refunded_cents < $payment->amount_cents)
                    <form class="admin-form commerce-refund-form" method="post"
                        action="{{ route('admin.finance.billing.refund', $payment) }}">
                        @csrf
                        <label>Amount (USD)
                            <input type="number" min="0.01" step="0.01" max="{{ number_format(($payment->amount_cents - $payment->refunded_cents) / 100, 2, '.', '') }}"
                                name="amount" required></label>
                        <label>Reason <input name="reason" minlength="8" maxlength="120" required></label>
                        <button type="submit">Request test-mode refund</button>
                    </form>
                @endif
            </div>
        @empty
            <p>No verified payments recorded.</p>
        @endforelse
    </section>
</x-layouts.admin>
