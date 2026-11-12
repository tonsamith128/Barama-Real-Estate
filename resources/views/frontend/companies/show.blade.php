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
        <section aria-labelledby="company-projects-heading">
            <header class="marketplace__header">
                <div>
                    <p class="marketplace__eyebrow">Published developments</p>
                    <h2 id="company-projects-heading">Projects by {{ $company->name }}</h2>
                </div>
            </header>
            <div class="marketplace__grid">
                @forelse ($projects as $project)
                    <article class="property-card">
                        <p class="property-card__eyebrow">{{ ucfirst(str_replace('_', ' ', $project->project_type)) }}</p>
                        <h3 class="property-card__title"><a href="{{ route('projects.show', $project->slug) }}">{{ $project->name }}</a></h3>
                        @if ($project->completion_status)
                            <p class="property-card__meta">{{ ucfirst(str_replace('_', ' ', $project->completion_status)) }}</p>
                        @endif
                    </article>
                @empty
                    <p class="notice">There are no published projects to display.</p>
                @endforelse
            </div>
        </section>

        <section aria-labelledby="company-listings-heading">
            <header class="marketplace__header">
                <div>
                    <p class="marketplace__eyebrow">Approved marketplace offers</p>
                    <h2 id="company-listings-heading">Properties from {{ $company->name }}</h2>
                </div>
                <a href="{{ route('properties.index', ['q' => $company->name]) }}">Find all listings</a>
            </header>
            <div class="marketplace__grid">
                @forelse ($listings as $listing)
                    <x-marketplace.listing-card :listing="$listing" />
                @empty
                    <p class="notice">There are no active property listings to display.</p>
                @endforelse
            </div>
        </section>
    </main>
</x-layouts.frontend>
