<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Geography;

use App\Models\Location;
use App\Models\LocationCountry;
use App\Services\Geography\CountryGovernanceManager;
use App\Services\Geography\LocationTreeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class GeographyController
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.geography.index', [
            'countries' => LocationCountry::query()->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('country_code')
                ->orderBy('sort_order')->orderBy('name')->paginate(20),
            'parentChoices' => Location::query()->where('status', 'active')
                ->orderBy('country_code')->orderBy('name')->limit(500)->get(),
            'packs' => DB::table('location_data_packs')->orderBy('country_code')->get(),
            'registryCount' => DB::table('location_hierarchy_registry')->count(),
        ]);
    }

    public function registerCountry(
        Request $request, CountryGovernanceManager $countries,
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'country_code' => ['required', 'regex:/^[A-Za-z]{2}$/',
                Rule::unique('location_countries', 'country_code')],
            'iso3' => ['required', 'regex:/^[A-Za-z]{3}$/',
                Rule::unique('location_countries', 'iso3')],
            'name' => ['required', 'string', 'max:160'],
            'native_name' => ['nullable', 'string', 'max:160'],
        ]);
        $countries->create($request->user(), $data);
        return back()->with('notice', 'Country registered as disabled; review before enabling.');
    }

    public function enable(Request $request, LocationCountry $country, CountryGovernanceManager $countries): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $countries->enable($request->user(), $country);
        return back()->with('notice', 'Country enabled.');
    }

    public function disable(Request $request, LocationCountry $country, CountryGovernanceManager $countries): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $countries->disable($request->user(), $country);
        return back()->with('notice', 'Country disabled.');
    }

    public function default(Request $request, LocationCountry $country, CountryGovernanceManager $countries): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $countries->chooseDefault($request->user(), $country);
        return back()->with('notice', 'Default country updated.');
    }

    /** @return array<string, mixed> */
    private function locationFields(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'alpha_dash', 'max:190',
                Rule::unique('locations', 'slug')->ignore($location?->getKey())],
            'type' => ['required', Rule::in(Location::TYPES)],
            'country_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'parent_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'sort_order' => ['nullable', 'integer', 'between:-100000,100000'],
            'status' => ['required', Rule::in(['active','inactive'])],
            'latitude' => ['nullable','numeric','between:-90,90','required_with:longitude'],
            'longitude' => ['nullable','numeric','between:-180,180','required_with:latitude'],
        ]);
    }

    public function storeLocation(Request $request, LocationTreeManager $locations): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $locations->create($request->user(), $this->locationFields($request));
        return back()->with('notice', 'Location added to the existing hierarchy.');
    }

    public function updateLocation(
        Request $request, Location $location, LocationTreeManager $locations,
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $locations->update($request->user(), $location, $this->locationFields($request, $location));
        return back()->with('notice', 'Location updated; existing property IDs retained.');
    }
}
