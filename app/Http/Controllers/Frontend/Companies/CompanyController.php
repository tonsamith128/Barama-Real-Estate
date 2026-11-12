<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Companies;

use App\Services\Marketplace\PublishedCompanyMedia;
use App\Services\Marketplace\PublishedCompanyPortfolio;
use App\Services\Seo\PublicDetailSeo;
use App\Domain\RealEstate\MarketplaceStatus;
use App\Http\Requests\Frontend\Companies\BrowseCompaniesRequest;
use App\Services\Marketplace\PublishedCompanySearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class CompanyController
{
    public function index(BrowseCompaniesRequest $request, PublishedCompanySearch $search, PublishedCompanyMedia $media): View
    {
        $companies = $search->browse($request->validated());

        return view('frontend.companies.index', [
            'companies' => $companies,
            'filters' => $request->validated(),
            'companyTypes' => MarketplaceStatus::COMPANY_TYPES,
            'companyMedia' => $media->forCompanies($companies->getCollection()),
        ]);
    }

    public function show(
        string $slug,
        PublishedCompanySearch $search,
        PublishedCompanyMedia $media,
        PublishedCompanyPortfolio $portfolio,
        PublicDetailSeo $seo,
    ): View {
        $company = $search->findPublicBySlug($slug);
        $mediaByCompany = $media->forCompanies(new EloquentCollection([$company]));

        return view('frontend.companies.show', [
            'company' => $company,
            'seo' => $seo->forCompany($company),
            ...$portfolio->preview($company),
            'companyMedia' => $mediaByCompany[$company->getKey()] ?? [],
        ]);
    }
}
