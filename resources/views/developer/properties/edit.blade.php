<x-layouts.admin :title="$property->title . ' — Edit Draft'">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Developer / Property Management</p>
        <h1>Edit property draft</h1><p>{{ $company->name }} — {{ $property->reference_code }}</p></div>
        <a href="{{ route('developer.properties.index', $company) }}">Back to properties</a>
    </header>
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="admin-card">
        <p>Only draft or rejected properties can be edited. Changes remain private until approved.</p>
        <form class="admin-form" method="post" action="{{ route('developer.properties.update', [$company, $property]) }}">
            @csrf @method('PUT')
            <label>Reference <input name="reference_code" required maxlength="80" value="{{ old('reference_code',$property->reference_code) }}"></label>
            <label>Title <input name="title" required maxlength="190" value="{{ old('title',$property->title) }}"></label>
            <label>Type <select name="property_type">
                @foreach($types as $type)<option value="{{ $type }}" @selected(old('property_type',$property->property_type)===$type)>{{ ucfirst($type) }}</option>@endforeach
            </select></label>
            <label>Project <select name="project_id"><option value="">No project</option>
                @foreach($projects as $project)<option value="{{ $project->id }}" @selected((string)old('project_id',$property->project_id)===(string)$project->id)>{{ $project->name }}</option>@endforeach
            </select></label>
            <label>Description <textarea name="description" rows="4" maxlength="10000">{{ old('description',$property->description) }}</textarea></label>
            <label>Address <input name="address_line" maxlength="255" value="{{ old('address_line',$property->address_line) }}"></label>
            <label>Bedrooms <input type="number" name="bedrooms" min="0" max="100" value="{{ old('bedrooms',$property->bedrooms) }}"></label>
            <label>Bathrooms <input type="number" name="bathrooms" min="0" max="100" value="{{ old('bathrooms',$property->bathrooms) }}"></label>
            <label>Area <input type="number" name="area_value" min="0" step=".01" value="{{ old('area_value',$property->area_value) }}"></label>
            <label>Area unit <select name="area_unit"><option value="">Not specified</option>
                @foreach(['m2','sqft','acre','hectare'] as $unit)<option value="{{ $unit }}" @selected(old('area_unit',$property->area_unit)===$unit)>{{ $unit }}</option>@endforeach
            </select></label>
            @foreach($property->listings as $listing)
                <fieldset><legend>Listing: {{ $listing->transaction_type }}</legend>
                    <label>Transaction <select name="transaction_type">
                        @foreach(['sale'=>'Sale','rent'=>'Rent'] as $value=>$label)<option value="{{ $value }}" @selected(old('transaction_type',$listing->transaction_type)===$value)>{{ $label }}</option>@endforeach
                    </select></label>
                    <label>Price <input type="number" step=".01" min="0" max="999999999999" name="price" required value="{{ old('price',$listing->price) }}"></label>
                    <label>Currency <select name="currency">
                        @foreach(['USD','KHR'] as $currency)<option value="{{ $currency }}" @selected(old('currency',$listing->currency)===$currency)>{{ $currency }}</option>@endforeach
                    </select></label>
                    <label>Negotiable <input type="checkbox" name="negotiable" value="1" @checked(old('negotiable',$listing->negotiable))></label>
                </fieldset>
            @endforeach
            <button type="submit">Save draft changes</button>
        </form>
    </section>
</x-layouts.admin>
