<x-layouts.admin title="Company Pricing Plans — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Developer / Finance</p>
            <h1>{{ $company->name }} — Business Plans</h1>
            <p>Review published pricing options and send a plan inquiry for administrator follow-up.</p>
        </div>
        <a href="{{ route('developer.dashboard') }}">Back to workspace</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($subscription)
        <section class="admin-card">
            <h2>Verified company entitlement</h2>
            <p>Plan #{{ $subscription->commercial_plan_id }} · {{ ucfirst($subscription->status) }} ·
                {{ number_format($subscription->listing_limit) }} listing allowance ·
                {{ $subscription->ends_at?->format('Y-m-d') ?? 'No scheduled expiration' }}</p>
            <p>Entitlements are created only by a verified paid provider webhook.</p>
        </section>
    @endif
    @if($orders->isNotEmpty())
        <section class="admin-card">
            <h2>Company invoices and orders</h2>
            <ul class="commerce-inquiry-list">
                @foreach($orders as $order)
                    <li>{{ $order->invoice?->invoice_number }} · {{ $order->plan_name }} ·
                        {{ $order->currency }} {{ number_format($order->amount_cents / 100, 2) }} ·
                        {{ $order->invoice?->status ?? 'Pending' }}
                        @if($order->invoice?->paid_at)
                            · <a href="{{ route('developer.finance.receipts.show', [$company, $order->invoice]) }}">View confirmation</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
    @if($inquiry)
        <section class="admin-card">
            <h2>Current inquiry</h2>
            <p>{{ $inquiry->plan?->name ?? 'Unavailable plan' }} · {{ ucfirst($inquiry->status) }} · Updated {{ $inquiry->updated_at?->format('Y-m-d') }}</p>
            <p>This is not a paid subscription and does not change your company permissions or listing limits.</p>
        </section>
    @endif

    <div class="commerce-plan-grid">
        @forelse($plans as $plan)
            <section class="admin-card commerce-plan-card">
                <h2>{{ $plan->name }}</h2>
                <p class="commerce-plan-card__price">{{ $plan->formattedPrice() }}
                    <small>/ {{ $plan->billing_period === 'once' ? 'one-time' : $plan->billing_period }}</small>
                </p>
                <p>{{ $plan->description }}</p>
                <p>Proposed listing allowance: {{ number_format($plan->listing_limit) }}</p>
                @if($billingEnabled && $plan->price_cents > 0)
                    <form method="post" action="{{ route('developer.finance.plans.checkout', [$company, $plan]) }}">
                        @csrf
                        <button type="submit">Open Stripe test checkout</button>
                    </form>
                @endif
                <form method="post" action="{{ route('developer.finance.plans.inquire', [$company, $plan]) }}">
                    @csrf
                    <button type="submit">Request plan information</button>
                </form>
            </section>
        @empty
            <section class="admin-card"><p>No company plans have been published.</p></section>
        @endforelse
    </div>
    <p class="admin-notice">Sandbox only. Checkout and recurring test mode are separately disabled by default.
        Enabling both test flags allows Stripe subscription simulations and signed renewal processing; no live money can be collected.
        A browser redirect never activates an entitlement. Payment confirmations are not tax invoices.</p>
</x-layouts.admin>
