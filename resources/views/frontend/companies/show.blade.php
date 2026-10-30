<x-layouts.frontend :title="$company->name . ' — Barama Real Estate'">
    <main class="marketplace" id="main-content">
        <nav aria-label="Breadcrumb" class="marketplace__breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('companies.index') }}">Companies</a>
        </nav>
        <article class="property-detail">
            <p class="marketplace__eyebrow">{{ ucfirst(str_replace('_', ' ', $company->company_type)) }}</p>
            <h1>{{ $company->name }}</h1>
            @if ($company->description)
                <div class="property-detail__description">{{ $company->description }}</div>
            @endif
        </article>
    </main>
</x-layouts.frontend>
