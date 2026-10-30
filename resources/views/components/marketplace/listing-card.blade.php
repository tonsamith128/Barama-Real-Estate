@props(['listing'])

<article class="property-card">
    @php($cover = $listing->mediaAttachments->first()?->asset)
    @if($cover)
        <a href="{{ route('properties.show', $listing->slug) }}">
            <img class="property-card__image" src="{{ route('media.demo.show', $cover->checksum_sha256) }}"
                alt="{{ $cover->alt_text ?: $listing->property->title }}" loading="lazy">
        </a>
    @endif
    <div class="property-card__eyebrow">
        {{ ucfirst($listing->transaction_type) }} · {{ ucfirst($listing->property->property_type) }}
    </div>
    <h2 class="property-card__title">
        <a href="{{ route('properties.show', $listing->slug) }}">{{ $listing->property->title }}</a>
    </h2>
    <p class="property-card__meta">
        {{ $listing->property->company->name }}
        @if ($listing->property->address_line)
            · {{ $listing->property->address_line }}
        @endif
    </p>
    <p class="property-card__price">
        {{ $listing->currency }} {{ number_format((float) $listing->price, 2) }}
        @if ($listing->pricing_period)
            <small>/ {{ $listing->pricing_period }}</small>
        @endif
    </p>
    <p class="property-card__specs">
        @if ($listing->property->bedrooms !== null)
            {{ $listing->property->bedrooms }} bedrooms
        @endif
        @if ($listing->property->bathrooms !== null)
            · {{ $listing->property->bathrooms }} bathrooms
        @endif
        @if ($listing->property->area_value !== null)
            · {{ $listing->property->area_value }} {{ $listing->property->area_unit }}
        @endif
    </p>
</article>
