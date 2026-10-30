<x-layouts.admin :title="$company->name . ' — Property Management'">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Barama Real Estate / Developer</p>
        <h1>{{ $company->name }} · Properties</h1>
        <p>New properties remain private until an administrator approves them.</p></div>
        <a href="{{ route('developer.dashboard') }}">Your companies</a>
    </header>
    @if(session('notice'))<p class="admin-notice">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="admin-card"><h2>Create a property</h2>
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
        <label>Bedrooms <input type="number" name="bedrooms" min="0" max="100" value="{{ old('bedrooms') }}"></label>
        <label>Bathrooms <input type="number" name="bathrooms" min="0" max="100" value="{{ old('bathrooms') }}"></label>
        <label>Transaction <select name="transaction_type"><option value="sale">Sale</option><option value="rent">Rent</option></select></label>
        <label>Price <input type="number" name="price" min="0" step=".01" required></label>
        <label>Currency <select name="currency"><option value="USD">USD</option><option value="KHR">KHR</option></select></label>
        <button type="submit">Save private draft</button>
    </form></section>
    <section class="admin-card"><h2>Company properties</h2>
    <ul class="admin-company-list">@forelse($properties as $property)
        <li><span><strong>{{ $property->title }}</strong> · {{ ucfirst($property->status) }}
            @foreach($property->listings as $listing)<small> · {{ $listing->transaction_type }} {{ $listing->status }}</small>@endforeach</span>
            @if($property->status === 'draft')<form method="post" action="{{ route('developer.properties.submit', [$company, $property]) }}">@csrf
            <button type="submit">Submit for review</button></form>@endif</li>
        @empty<li>No property records yet.</li>@endforelse</ul>
    {{ $properties->links() }}</section>
</x-layouts.admin>
