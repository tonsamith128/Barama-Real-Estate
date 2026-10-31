<x-layouts.frontend title="Business Plans — Barama Real Estate">
    <main id="main-content" class="marketplace info-page">
        <p class="marketplace__eyebrow">{{ __('pricing.audience', [], $billingLocale) }}</p>
        <h1>{{ __('pricing.title', [], $billingLocale) }}</h1>
        <p class="info-page__lead">{{ __('pricing.intro', [], $billingLocale) }}</p>
        <nav aria-label="Language selection">
            <span>{{ __('pricing.language', [], $billingLocale) }}:</span>
            @foreach($availableLanguages as $code => $name)
                <a href="{{ route('pricing.index', ['lang' => $code]) }}" lang="{{ $code }}" @if($billingLocale === $code) aria-current="page" @endif>{{ $name }}</a>
            @endforeach
        </nav>
        <p class="info-page__note">{{ __('pricing.usd', [], $billingLocale) }}</p>

        <div class="commerce-plan-grid">
            @forelse($plans as $plan)
                <article class="commerce-plan-card">
                    <h2>{{ $plan->name }}</h2>
                    <p class="commerce-plan-card__price">{{ $plan->formattedPrice() }}
                        <small>/ {{ __('pricing.unit_'.$plan->billing_period, [], $billingLocale) }}</small>
                    </p>
                    <p>{{ $plan->description }}</p>
                    <p>{{ __('pricing.capacity', ['count' => number_format($plan->listing_limit)], $billingLocale) }}</p>
                    <p><a href="{{ route('contact') }}">{{ __('pricing.ask', [], $billingLocale) }}</a></p>
                </article>
            @empty
                <p>{{ __('pricing.empty', [], $billingLocale) }} <a href="{{ route('contact') }}">{{ __('pricing.contact', [], $billingLocale) }}</a> {{ __('pricing.empty_more', [], $billingLocale) }}</p>
            @endforelse
        </div>
        <p class="info-page__note">{{ __('pricing.note', [], $billingLocale) }}</p>
    </main>
</x-layouts.frontend>
