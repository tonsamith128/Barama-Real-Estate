<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Companies;

use App\Services\Marketplace\PublishedCompanyMedia;
use App\Services\Marketplace\PublishedCompanySearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class CompanyController
{
    public function index(PublishedCompanySearch $search, PublishedCompanyMedia $media): View
    {
        $companies = $search->browse();

        return view('frontend.companies.index', [
            'companies' => $companies,
            'companyMedia' => $media->forCompanies($companies->getCollection()),
        ]);
    }

    public function show(
        string $slug,
        PublishedCompanySearch $search,
        PublishedCompanyMedia $media,
    ): View {
        $company = $search->findPublicBySlug($slug);
        $mediaByCompany = $media->forCompanies(new EloquentCollection([$company]));

        return view('frontend.companies.show', [
            'company' => $company,
            'companyMedia' => $mediaByCompany[$company->getKey()] ?? [],
        ]);
    }
}
