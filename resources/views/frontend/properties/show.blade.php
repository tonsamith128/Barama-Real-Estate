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

            @if(session('notice'))<p class="notice" role="status">{{ session('notice') }}</p>@endif
            <section class="inquiry-form" aria-labelledby="inquire-heading">
                <h2 id="inquire-heading">Ask about this property</h2>
                @if($errors->any())<div class="notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <form method="post" action="{{ route('properties.inquiries.store', $listing->slug) }}">
                    @csrf
                    <label class="field">Your name
                        <input class="field__control" name="full_name" maxlength="120" required value="{{ old('full_name', auth()->user()?->name) }}">
                    </label>
                    <label class="field">Email
                        <input class="field__control" type="email" name="email" maxlength="190" required value="{{ old('email', auth()->user()?->email) }}">
                    </label>
                    <label class="field">Phone (optional)
                        <input class="field__control" name="phone" maxlength="50" value="{{ old('phone') }}">
                    </label>
                    <label class="field">Message
                        <textarea class="field__control" name="message" rows="5" maxlength="4000" required>{{ old('message') }}</textarea>
                    </label>
                    <input name="website" type="text" tabindex="-1" autocomplete="off" aria-hidden="true" class="inquiry-honeypot">
                    <label class="field"><span>
                        <input type="checkbox" name="consent" value="1" required>
                        I agree to be contacted about this inquiry.
                    </span></label>
                    <button class="button" type="submit">Send inquiry</button>
                </form>
            </section>
        </article>
    </main>
</x-layouts.frontend>
