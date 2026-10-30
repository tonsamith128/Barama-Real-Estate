<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Companies;

use App\Services\Marketplace\PublishedCompanySearch;
use Illuminate\Contracts\View\View;

final class CompanyController
{
    public function index(PublishedCompanySearch $search): View
    {
        return view('frontend.companies.index', [
            'companies' => $search->browse(),
        ]);
    }

    public function show(string $slug, PublishedCompanySearch $search): View
    {
        return view('frontend.companies.show', [
            'company' => $search->findPublicBySlug($slug),
        ]);
    }
}
