<x-layouts.frontend :title="$company->name . ' — Barama Real Estate'">
    <main class="marketplace" id="main-content">
        <nav aria-label="Breadcrumb" class="marketplace__breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('companies.index') }}">Companies</a>
        </nav>
        <article class="property-detail">
            <p class="marketplace__eyebrow">{{ ucfirst(str_replace('_', ' ', $company->company_type)) }}</p>

            @if(isset($companyMedia['logo']))
                <img loading="lazy" decoding="async"
                    src="{{ route('media.company.show', ['slug' => $company->slug, 'collection' => 'logo']) }}"
                    alt="{{ $company->name }} company logo" width="96" height="96">
            @endif
            <h1>{{ $company->name }}</h1>
            @if(isset($companyMedia['cover']))
                <img loading="lazy" decoding="async"
                    src="{{ route('media.company.show', ['slug' => $company->slug, 'collection' => 'cover']) }}"
                    alt="{{ $company->name }} company cover image"
                    width="960" height="320" style="width:100%;height:auto;max-height:320px;object-fit:cover">
            @endif
            @if ($company->description)
                <div class="property-detail__description">{{ $company->description }}</div>
            @endif
        </article>
    </main>
</x-layouts.frontend>
