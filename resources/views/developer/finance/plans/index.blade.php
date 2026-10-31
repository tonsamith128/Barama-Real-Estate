<x-layouts.admin title="Company Pricing Plans — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Developer / Finance</p>
            <h1>{{ $company->name }} — Business Plans</h1>
            <p>Review published pricing options and send a plan inquiry for administrator follow-up.</p>
        </div>
        <a href="{{ route('developer.dashboard') }}">Back to workspace</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
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
                <form method="post" action="{{ route('developer.finance.plans.inquire', [$company, $plan]) }}">
                    @csrf
                    <button type="submit">Request plan information</button>
                </form>
            </section>
        @empty
            <section class="admin-card"><p>No company plans have been published.</p></section>
        @endforelse
    </div>
    <p class="admin-notice">This feature records interest only. There is no checkout, automatic entitlement, invoice or payment confirmation.</p>
</x-layouts.admin>
