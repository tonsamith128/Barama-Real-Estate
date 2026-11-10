<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Properties;

use App\Domain\RealEstate\MarketplaceStatus;
use App\Models\Company;
use App\Models\Project;
use App\Models\Location;
use App\Models\Property;
use App\Services\Developer\CompanyCatalogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

final class DeveloperPropertyController
{
    public function index(Request $request, Company $company, CompanyCatalogWriter $writer): View
    {
        $writer->assertCanManage($request->user(), $company);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'rejected'])],
        ]);
        $keyword = trim((string) ($filters['q'] ?? ''));
        $query = $company->properties()->with(['listings.mediaAttachments.asset']);
        if ($keyword !== '') {
            // Escape SQL LIKE wildcards so a literal search stays literal.
            $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
            $query->where(function ($match) use ($needle): void {
                $match->where('title', 'like', '%'.$needle.'%')
                    ->orWhere('reference_code', 'like', '%'.$needle.'%');
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('developer.properties.index', [
            'company' => $company,
            'properties' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => ['q' => $keyword, 'status' => $filters['status'] ?? ''],
            'projects' => Project::query()->where('company_id', $company->getKey())->orderBy('name')->get(),
            'types' => MarketplaceStatus::PROPERTY_TYPES,
            'locations' => Location::query()->where('status', 'active')->orderBy('country_code')->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function store(Request $request, Company $company, CompanyCatalogWriter $writer): RedirectResponse
    {
        $writer->assertCanManage($request->user(), $company);
        $validated = $this->validated($request, $company);
        $writer->create($request->user(), $company, $validated);
        return redirect()->route('developer.properties.index', $company)
            ->with('notice', 'Property saved as a private draft.');
    }

    public function edit(Request $request, Company $company, Property $property, CompanyCatalogWriter $writer): View
    {
        $writer->assertCanManage($request->user(), $company);
        abort_unless($property->company_id === $company->getKey(), 404);
        abort_unless(in_array($property->status, ['draft', 'rejected'], true), 409);
        return view('developer.properties.edit', [
            'company' => $company,
            'property' => $property->load('listings'),
            'projects' => Project::query()->where('company_id', $company->getKey())->orderBy('name')->get(),
            'types' => MarketplaceStatus::PROPERTY_TYPES,
            'locations' => Location::query()->where('status', 'active')
                ->orderBy('country_code')->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function update(Request $request, Company $company, Property $property, CompanyCatalogWriter $writer): RedirectResponse
    {
        $writer->assertCanManage($request->user(), $company);
        abort_unless($property->company_id === $company->getKey(), 404);
        abort_unless(in_array($property->status, ['draft', 'rejected'], true), 409);
        $validated = $this->validated($request, $company, $property);
        $writer->reviseDraft($request->user(), $company, $property, $validated);
        return redirect()->route('developer.properties.index', $company)
            ->with('notice', 'Property updated as a private draft.');
    }

    private function validated(Request $request, Company $company, ?Property $property = null): array
    {
        return $request->validate([
            'reference_code' => ['required','string','max:80',Rule::unique('properties','reference_code')->ignore($property?->getKey())],
            'title' => ['required','string','min:4','max:190'],
            'property_type' => ['required',Rule::in(MarketplaceStatus::PROPERTY_TYPES)],
            'project_id' => ['nullable','integer',Rule::exists('projects','id')->where('company_id', $company->getKey())],
            'description' => ['nullable','string','max:10000'],
            'address_line' => ['nullable','string','max:255'],
            'bedrooms' => ['nullable','integer','between:0,100'],
            'bathrooms' => ['nullable','integer','between:0,100'],
            'area_value' => ['nullable','numeric','between:0,9999999999'],
            'area_unit' => ['nullable',Rule::in(['m2','sqft','acre','hectare'])],
            'location_id' => ['nullable', 'integer', Rule::exists('locations','id')->where('status','active')],
            'parking_spaces' => ['nullable','integer','between:0,1000'],
            'floor_number' => ['nullable','integer','between:-30,1000'],
            'total_floors' => ['nullable','integer','between:0,1000'],
            'land_area_value' => ['nullable','numeric','between:0,999999999999'],
            'land_area_unit' => ['nullable',Rule::in(['m2','sqft','acre','hectare'])],
            'furnishing_status' => ['nullable',Rule::in(['unfurnished','partly_furnished','furnished'])],
            'year_built' => ['nullable','integer','between:1800,2200'],
            'latitude' => ['nullable','numeric','between:-90,90','required_with:longitude'],
            'longitude' => ['nullable','numeric','between:-180,180','required_with:latitude'],
            'transaction_type' => ['required',Rule::in(['sale','rent'])],
            'price' => ['required','numeric','between:0,999999999999'],
            'currency' => ['required',Rule::in(['USD','KHR'])],
            'pricing_period' => ['nullable','string','max:24'],
            'negotiable' => ['nullable','boolean'],
            'deposit_amount' => ['nullable','numeric','between:0,999999999999'],
            'available_from' => ['nullable','date_format:Y-m-d'],
        ]);
    }

    public function submit(Request $request, Company $company, Property $property, CompanyCatalogWriter $writer): RedirectResponse
    {
        $writer->submitForReview($request->user(), $company, $property);
        return redirect()->route('developer.properties.index', $company)
            ->with('notice', 'Property submitted for administrator review.');
    }
}
