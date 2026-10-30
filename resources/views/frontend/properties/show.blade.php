<x-layouts.frontend :title="$listing->property->title . ' — Barama Real Estate'">
    <main class="marketplace" id="main-content">
        <nav aria-label="Breadcrumb" class="marketplace__breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('properties.index') }}">Properties</a>
        </nav>

        <article class="property-detail">
            @php($cover = $listing->mediaAttachments->first()?->asset)
            @if($cover)
                <img class="property-detail__image" src="{{ route('media.demo.show', $cover->checksum_sha256) }}"
                    alt="{{ $cover->alt_text ?: $listing->property->title }}">
            @endif
            <p class="marketplace__eyebrow">
                {{ ucfirst($listing->transaction_type) }} ·
                {{ ucfirst($listing->property->property_type) }}
            </p>
            <h1>{{ $listing->property->title }}</h1>
            <p>{{ $listing->property->company->name }}</p>
            <p class="property-detail__price">
                {{ $listing->currency }} {{ number_format((float) $listing->price, 2) }}
                @if ($listing->pricing_period)
                    / {{ $listing->pricing_period }}
                @endif
            </p>

            @if ($listing->property->address_line)
                <h2>Location</h2>
                <p>{{ $listing->property->address_line }}</p>
            @endif

            @if ($listing->property->description)
                <h2>Property details</h2>
                <div class="property-detail__description">{{ $listing->property->description }}</div>
            @endif

            <p class="notice">
                Contact and inquiry workflows are not yet available in the Laravel development edition.
            </p>
        </article>
    </main>
</x-layouts.frontend>
