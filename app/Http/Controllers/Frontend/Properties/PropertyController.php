<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Properties;

use App\Domain\RealEstate\MarketplaceStatus;
use App\Http\Requests\Frontend\Properties\BrowsePropertiesRequest;
use App\Services\Marketplace\PublishedListingSearch;
use Illuminate\Contracts\View\View;

final class PropertyController
{
    public function index(
        BrowsePropertiesRequest $request,
        PublishedListingSearch $search,
    ): View {
        $filters = $request->validated();

        return view('frontend.properties.index', [
            'listings' => $search->browse($filters),
            'filters' => $filters,
            'propertyTypes' => MarketplaceStatus::PROPERTY_TYPES,
        ]);
    }

    public function show(string $slug, PublishedListingSearch $search): View
    {
        return view('frontend.properties.show', [
            'listing' => $search->findPublicBySlug($slug),
        ]);
    }
}
