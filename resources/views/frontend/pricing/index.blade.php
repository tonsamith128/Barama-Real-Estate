<x-layouts.frontend title="Business Plans — Barama Real Estate">
    <main id="main-content" class="marketplace info-page">
        <p class="marketplace__eyebrow">For real estate companies</p>
        <h1>Business plans</h1>
        <p class="info-page__lead">Explore available publishing plan options for your company. Plans are informational until checkout, verified billing and activation are launched.</p>

        <div class="commerce-plan-grid">
            @forelse($plans as $plan)
                <article class="commerce-plan-card">
                    <h2>{{ $plan->name }}</h2>
                    <p class="commerce-plan-card__price">{{ $plan->formattedPrice() }}
                        <small>/ {{ $plan->billing_period === 'once' ? 'one-time' : $plan->billing_period }}</small>
                    </p>
                    <p>{{ $plan->description }}</p>
                    <p>Up to {{ number_format($plan->listing_limit) }} listings in the proposed plan.</p>
                    <p><a href="{{ route('contact') }}">Ask about this plan</a></p>
                </article>
            @empty
                <p>Plan options have not been published yet. <a href="{{ route('contact') }}">Contact us</a> for more information.</p>
            @endforelse
        </div>
        <p class="info-page__note">These are published pricing options, not an active checkout. Requesting information does not charge a card, create an invoice, or activate a subscription.</p>
    </main>
</x-layouts.frontend>
