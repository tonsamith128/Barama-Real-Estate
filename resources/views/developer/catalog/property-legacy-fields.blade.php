@include('developer.catalog.location-choice', ['selectedLocation' => $property?->location_id])
<label>Parking spaces <input type="number" name="parking_spaces" min="0" max="1000" value="{{ old('parking_spaces', $property?->parking_spaces) }}"></label>
<label>Floor number <input type="number" name="floor_number" min="-30" max="1000" value="{{ old('floor_number', $property?->floor_number) }}"></label>
<label>Total floors <input type="number" name="total_floors" min="0" max="1000" value="{{ old('total_floors', $property?->total_floors) }}"></label>
<label>Land area <input type="number" name="land_area_value" min="0" step=".01" value="{{ old('land_area_value', $property?->land_area_value) }}"></label>
<label>Land area unit <select name="land_area_unit">
    <option value="">Not specified</option>
    @foreach(['m2','sqft','acre','hectare'] as $unit)
        <option value="{{ $unit }}" @selected(old('land_area_unit', $property?->land_area_unit) === $unit)>{{ $unit }}</option>
    @endforeach
</select></label>
<label>Furnishing <select name="furnishing_status">
    <option value="">Not specified</option>
    @foreach(['unfurnished'=>'Unfurnished','partly_furnished'=>'Partly furnished','furnished'=>'Furnished'] as $code=>$label)
        <option value="{{ $code }}" @selected(old('furnishing_status', $property?->furnishing_status) === $code)>{{ $label }}</option>
    @endforeach
</select></label>
<label>Year built <input type="number" name="year_built" min="1800" max="2200" value="{{ old('year_built', $property?->year_built) }}"></label>
<label>Latitude <input type="number" name="latitude" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $property?->latitude) }}"></label>
<label>Longitude <input type="number" name="longitude" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $property?->longitude) }}"></label>