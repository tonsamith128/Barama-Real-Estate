<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Properties;

use App\Domain\RealEstate\MarketplaceStatus;
use App\Models\Company;
use App\Models\Project;
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
        return view('developer.properties.index', [
            'company' => $company,
            'properties' => $company->properties()->with('listings')->latest()->paginate(15),
            'projects' => Project::query()->where('company_id', $company->getKey())->orderBy('name')->get(),
            'types' => MarketplaceStatus::PROPERTY_TYPES,
        ]);
    }

    public function store(Request $request, Company $company, CompanyCatalogWriter $writer): RedirectResponse
    {
        $writer->assertCanManage($request->user(), $company);
        $validated = $request->validate([
            'reference_code' => ['required','string','max:80',Rule::unique('properties','reference_code')],
            'title' => ['required','string','min:4','max:190'],
            'property_type' => ['required',Rule::in(MarketplaceStatus::PROPERTY_TYPES)],
            'project_id' => ['nullable','integer',Rule::exists('projects','id')->where('company_id', $company->getKey())],
            'description' => ['nullable','string','max:10000'],
            'address_line' => ['nullable','string','max:255'],
            'bedrooms' => ['nullable','integer','between:0,100'],
            'bathrooms' => ['nullable','integer','between:0,100'],
            'area_value' => ['nullable','numeric','between:0,9999999999'],
            'area_unit' => ['nullable',Rule::in(['m2','sqft','acre','hectare'])],
            'transaction_type' => ['required',Rule::in(['sale','rent'])],
            'price' => ['required','numeric','between:0,999999999999'],
            'currency' => ['required',Rule::in(['USD','KHR'])],
            'pricing_period' => ['nullable','string','max:24'],
            'negotiable' => ['nullable','boolean'],
        ]);
        $writer->create($request->user(), $company, $validated);
        return redirect()->route('developer.properties.index', $company)
            ->with('notice', 'Property saved as a private draft.');
    }

    public function submit(Request $request, Company $company, Property $property, CompanyCatalogWriter $writer): RedirectResponse
    {
        $writer->submitForReview($request->user(), $company, $property);
        return redirect()->route('developer.properties.index', $company)
            ->with('notice', 'Property submitted for administrator review.');
    }
}
