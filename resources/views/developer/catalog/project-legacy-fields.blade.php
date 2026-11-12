@include('developer.catalog.location-choice', ['selectedLocation' => $project?->location_id])
<label>Project address <input name="address_line" maxlength="255" value="{{ old('address_line', $project?->address_line) }}"></label>
<label>Latitude <input name="latitude" type="number" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $project?->latitude) }}"></label>
<label>Longitude <input name="longitude" type="number" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $project?->longitude) }}"></label>
<label>Launch date <input name="launch_date" type="date" value="{{ old('launch_date', $project?->launch_date?->format('Y-m-d')) }}"></label>
<label>Expected completion <input name="expected_completion_date" type="date" value="{{ old('expected_completion_date', $project?->expected_completion_date?->format('Y-m-d')) }}"></label>
<label>Actual completion <input name="completed_at" type="date" value="{{ old('completed_at', $project?->completed_at?->format('Y-m-d')) }}"></label>