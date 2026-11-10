<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Properties;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Property;
use App\Services\Developer\ListingOfferManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ListingOfferController
{
    /** @return array<string, mixed> */
    private function fields(Request $request): array
    {
        return $request->validate([
            'transaction_type' => ['required', Rule::in(['sale', 'rent'])],
            'price' => ['required', 'numeric', 'between:0,999999999999'],
            'currency' => ['required', Rule::in(['USD', 'KHR'])],
            'pricing_period' => ['nullable', Rule::in(['month', 'week', 'day', 'year', 'one_time'])],
            'deposit_amount' => ['nullable', 'numeric', 'between:0,999999999999'],
            'available_from' => ['nullable', 'date_format:Y-m-d'],
            'negotiable' => ['nullable', 'boolean'],
        ]);
    }

    public function store(
        Request $request, Company $company, Property $property, ListingOfferManager $offers,
    ): RedirectResponse {
        $offers->create($request->user(), $company, $property, $this->fields($request));
        return back()->with('notice', 'Additional offer saved privately for review.');
    }

    public function update(
        Request $request, Company $company, Property $property,
        Listing $offer, ListingOfferManager $offers,
    ): RedirectResponse {
        $offers->revise($request->user(), $company, $property, $offer, $this->fields($request));
        return back()->with('notice', 'Offer revised. Submit it for administrator review.');
    }

    public function submit(
        Request $request, Company $company, Property $property,
        Listing $offer, ListingOfferManager $offers,
    ): RedirectResponse {
        $offers->submit($request->user(), $company, $property, $offer);
        return back()->with('notice', 'Offer submitted; other published offers were not changed.');
    }
}
