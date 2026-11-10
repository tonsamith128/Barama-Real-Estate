<x-layouts.admin :title="$company->name . ' — Property Management'">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Barama Real Estate / Developer</p>
        <h1>{{ $company->name }} · Properties</h1>
        <p>New properties remain private until an administrator approves them.</p></div>
        <a href="{{ route('developer.dashboard') }}">Your companies</a>
    </header>
    @if(session('notice'))<p class="admin-notice">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="admin-card"><h2>Create a property</h2><p><a href="{{ route('developer.projects.index', $company) }}">Manage development projects</a></p>
    <form method="post" action="{{ route('developer.properties.store', $company) }}" class="admin-form">
        @csrf
        <label>Reference code <input name="reference_code" maxlength="80" value="{{ old('reference_code') }}" required></label>
        <label>Title <input name="title" maxlength="190" value="{{ old('title') }}" required></label>
        <label>Property type <select name="property_type" required>@foreach($types as $type)
            <option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></label>
        <label>Development project (optional) <select name="project_id"><option value="">None</option>
            @foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></label>
        <label>Location/address <input name="address_line" value="{{ old('address_line') }}"></label>
        <label>Description <textarea name="description" rows="4">{{ old('description') }}</textarea></label>
        @include('developer.catalog.property-legacy-fields', ['property' => null])
        <label>Bedrooms <input type="number" name="bedrooms" min="0" max="100" step="0.5" value="{{ old('bedrooms') }}"></label>
        <label>Bathrooms <input type="number" name="bathrooms" min="0" max="100" step="0.5" value="{{ old('bathrooms') }}"></label>
        <label>Transaction <select name="transaction_type"><option value="sale">Sale</option><option value="rent">Rent</option></select></label>
        <label>Price <input type="number" name="price" min="0" step=".01" required></label>
        <label>Currency <select name="currency"><option value="USD">USD</option><option value="KHR">KHR</option></select></label>
        @include('developer.catalog.listing-legacy-fields', ['listing' => null])
        <button type="submit">Save private draft</button>
    </form></section>
    <section class="admin-card">
        <h2>Company properties &amp; galleries</h2>
        <form class="property-workflow-filters" method="get" action="{{ route('developer.properties.index', $company) }}" role="search" aria-label="Filter company properties">
            <label>Search title or reference
                <input type="search" name="q" maxlength="120" value="{{ $filters['q'] }}" placeholder="Search your company catalog">
            </label>
            <label>Review status
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach(['draft' => 'Draft', 'pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Needs revision'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit">Apply filters</button>
            <a href="{{ route('developer.properties.index', $company) }}">Reset</a>
        </form>
        <p class="property-workflow-result">{{ number_format($properties->total()) }} matching properties</p>
        <p>Images remain private until the listing passes administrator approval. Up to 20 JPEG, PNG or WebP photos (12 MB each) per draft listing.</p>
        <ul class="gallery-property-list">
            @forelse($properties as $property)
                <li>
                    <header class="gallery-property-header">
                        <strong>{{ $property->title }}</strong>
                        <span>{{ ucfirst($property->status) }}</span>
                    </header>
                    <section class="admin-card">
                        <h3>Property amenities</h3>
                        <p>@forelse($property->amenities as $amenity){{ $amenity->name }}@unless($loop->last), @endunless
                            @empty None selected @endforelse</p>
                        @if(in_array($property->status, ['draft', 'rejected'], true))
                            <form method="post" action="{{ route('developer.properties.amenities.update', [$company, $property]) }}">
                                @csrf @method('PUT')
                                @foreach($amenities as $amenity)
                                    <label><input type="checkbox" name="amenity_ids[]" value="{{ $amenity->id }}"
                                        @checked($property->amenities->contains('id', $amenity->id))>
                                        {{ $amenity->name }}
                                    </label>
                                @endforeach
                                <button type="submit">Save amenities</button>
                            </form>
                        @endif
                    </section>
                    @if(in_array($property->status, ['draft','approved'], true) && $property->listings->count() < 10)
                        <details><summary>Create an additional sale or rental offer</summary>
                            <form class="admin-form" method="post" action="{{ route('developer.properties.offers.store', [$company, $property]) }}">
                                @csrf
                                <label>Transaction <select name="transaction_type">
                                    <option value="sale">Sale</option><option value="rent">Rent</option>
                                </select></label>
                                <label>Price <input type="number" name="price" step=".01" min="0" required></label>
                                <label>Currency <select name="currency"><option>USD</option><option>KHR</option></select></label>
                                @include('developer.catalog.listing-legacy-fields', ['listing' => null])
                                <label>Negotiable <input type="checkbox" name="negotiable" value="1"></label>
                                <button type="submit">Save private offer</button>
                            </form>
                        </details>
                    @endif
                    @foreach($property->listings as $listing)
                        <section class="listing-gallery" aria-label="Photos for {{ $property->title }}">
                            <p><strong>{{ ucfirst($listing->transaction_type) }} listing</strong> · {{ $listing->status }} · {{ $listing->mediaAttachments->count() }} / 20 images</p>
                            @if(in_array($listing->status, ['draft', 'rejected'], true))
                                <details><summary>Edit this offer (not other published offers)</summary>
                                    <form class="admin-form" method="post" action="{{ route('developer.properties.offers.update', [$company, $property, $listing]) }}">
                                        @csrf @method('PUT')
                                        <label>Transaction <select name="transaction_type">
                                            <option value="sale" @selected($listing->transaction_type==='sale')>Sale</option>
                                            <option value="rent" @selected($listing->transaction_type==='rent')>Rent</option>
                                        </select></label>
                                        <label>Price <input type="number" step=".01" min="0" name="price" value="{{ $listing->price }}" required></label>
                                        <label>Currency <select name="currency">
                                            <option @selected($listing->currency==='USD')>USD</option>
                                            <option @selected($listing->currency==='KHR')>KHR</option>
                                        </select></label>
                                        @include('developer.catalog.listing-legacy-fields', ['listing' => $listing])
                                        <label>Negotiable <input type="checkbox" name="negotiable" value="1" @checked($listing->negotiable)></label>
                                        <button type="submit">Update private offer</button>
                                    </form>
                                </details>
                            @endif
                            @if($listing->status === 'draft' && in_array($property->status, ['draft','approved'], true))
                                <form method="post" action="{{ route('developer.properties.offers.submit', [$company, $property, $listing]) }}">
                                    @csrf<button type="submit">Submit this offer only</button>
                                </form>
                            @endif
                            @if($listing->mediaAttachments->isNotEmpty())
                                <ul class="listing-gallery__images">
                                    @foreach($listing->mediaAttachments as $link)
                                        <li>
                                            @if(in_array($property->status, ['draft', 'approved'], true) && $listing->status === 'draft')
                                                <img src="{{ route('developer.gallery.preview', [$company, $listing, $link->asset]) }}"
                                                    alt="{{ $link->asset->alt_text ?: $property->title }}" loading="lazy">
                                                @if($link->is_primary)<strong>Primary photo</strong>@endif
                                                @foreach(['primary'=>'Make primary', 'up'=>'Move up', 'down'=>'Move down'] as $action=>$label)
                                                    <form method="post" action="{{ route('developer.gallery.order', [$company, $listing, $link]) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="{{ $action }}">
                                                        <button type="submit">{{ $label }}</button>
                                                    </form>
                                                @endforeach
                                                <form method="post" action="{{ route('developer.gallery.destroy', [$company, $listing, $link]) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit">Remove</button>
                                                </form>
                                            @else
                                                <span>{{ $link->asset->original_name }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if(in_array($property->status, ['draft', 'approved'], true) && $listing->status === 'draft')
                                <form class="listing-gallery__upload" method="post"
                                    enctype="multipart/form-data"
                                    action="{{ route('developer.gallery.store', [$company, $listing]) }}">
                                    @csrf
                                    <label>Image <input type="file" name="image" required accept="image/jpeg,image/png,image/webp"></label>
                                    <label>Alternative text <input name="alt_text" maxlength="255" placeholder="Describe the photo"></label>
                                    <button type="submit">Upload private photo</button>
                                </form>
                            @endif
                        </section>
                    @endforeach
                    <p><a href="{{ route('developer.properties.history', [$company, $property]) }}">Revision history</a></p>
                    @if(in_array($property->status, ['draft', 'rejected'], true))
                        <p><a href="{{ route('developer.properties.edit', [$company, $property]) }}">Edit property draft</a></p>
                    @endif
                    @if($property->status === 'draft')
                        <form method="post" action="{{ route('developer.properties.submit', [$company, $property]) }}">
                            @csrf<button type="submit">Submit for administrator review</button>
                        </form>
                    @endif
                </li>
            @empty
                <li>No property records yet.</li>
            @endforelse
        </ul>
        {{ $properties->links() }}
    </section>
</x-layouts.admin>
