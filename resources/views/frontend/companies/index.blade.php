<x-layouts.frontend title="Property Companies — Barama Real Estate">
    <main class="marketplace" id="main-content">
        <header class="marketplace__header">
            <div>
                <p class="marketplace__eyebrow">Barama Real Estate</p>
                <h1>Property Companies</h1>
                <p>Explore approved and published companies.</p>
            </div>
            <a href="{{ route('home') }}">Home</a>
        </header>
        <div class="marketplace__grid">
            @forelse ($companies as $company)
                <article class="property-card">
                    <h2 class="property-card__title"><a href="{{ route('companies.show', $company->slug) }}">{{ $company->name }}</a></h2>
                    <p class="property-card__meta">{{ ucfirst(str_replace('_', ' ', $company->company_type)) }}</p>
                    @if ($company->description)
                        <p>{{ Str::limit($company->description, 150) }}</p>
                    @endif
                </article>
            @empty
                <p class="notice">No public companies are available yet.</p>
            @endforelse
        </div>
        {{ $companies->links() }}
    </main>
</x-layouts.frontend>
