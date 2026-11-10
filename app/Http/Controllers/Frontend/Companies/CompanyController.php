<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Companies;

use App\Domain\RealEstate\MarketplaceStatus;
use App\Http\Requests\Frontend\Companies\BrowseCompaniesRequest;
use App\Services\Marketplace\PublishedCompanyPortfolio;
use App\Services\Marketplace\PublishedCompanySearch;
use Illuminate\Contracts\View\View;

final class CompanyController
{
    public function index(BrowseCompaniesRequest $request, PublishedCompanySearch $search): View
    {
        $filters = $request->validated();

        return view('frontend.companies.index', [
            'companies' => $search->browse($filters),
            'filters' => $filters,
            'companyTypes' => MarketplaceStatus::COMPANY_TYPES,
        ]);
    }

    public function show(
        string $slug,
        PublishedCompanySearch $search,
        PublishedCompanyPortfolio $portfolio,
    ): View {
        $company = $search->findPublicBySlug($slug);

        return view('frontend.companies.show', [
            'company' => $company,
            ...$portfolio->preview($company),
        ]);
    }
}
