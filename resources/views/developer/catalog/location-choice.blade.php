<label>Geographic location
    <select name="location_id">
        <option value="">Not specified</option>
        @foreach($locations as $location)
            <option value="{{ $location->id }}" @selected((string) old('location_id', $selectedLocation ?? '') === (string) $location->id)>
                {{ strtoupper($location->country_code ?? '') }} · {{ ucfirst(str_replace('_', ' ', $location->type)) }} · {{ $location->name }}
            </option>
        @endforeach
    </select>
</label>