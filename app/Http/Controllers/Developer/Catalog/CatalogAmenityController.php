<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Catalog;

use App\Models\Company;
use App\Models\Project;
use App\Models\Property;
use App\Services\Developer\CatalogAmenityManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CatalogAmenityController
{
    /** @return array<int, int> */
    private function ids(Request $request): array
    {
        $data = $request->validate([
            'amenity_ids' => ['sometimes', 'array', 'max:100'],
            'amenity_ids.*' => ['required', 'integer', 'distinct', 'exists:amenities,id'],
        ]);
        return array_map('intval', $data['amenity_ids'] ?? []);
    }

    public function project(
        Request $request, Company $company, Project $project,
        CatalogAmenityManager $amenities,
    ): RedirectResponse {
        $amenities->syncProject($request->user(), $company, $project, $this->ids($request));
        return back()->with('notice', 'Project amenities saved.');
    }

    public function property(
        Request $request, Company $company, Property $property,
        CatalogAmenityManager $amenities,
    ): RedirectResponse {
        $amenities->syncProperty($request->user(), $company, $property, $this->ids($request));
        return back()->with('notice', 'Property amenities saved.');
    }
}
