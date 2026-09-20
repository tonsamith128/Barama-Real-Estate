<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\MarketplaceRepository;

final class CompanyController
{
    public function index(Request $request): Response
    {
        $repository = new MarketplaceRepository();
        $filters = [
            'q' => mb_substr(trim((string) $request->input('q', '')), 0, 100),
            'type' => trim((string) $request->input('type', '')),
            'location' => trim((string) $request->input('location', '')),
        ];

        $page = filter_var(
            $request->input('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return Response::html(View::render('company/index', [
            'pageTitle' => 'Real Estate Developers',
            'activeArea' => 'company',
            'inventory' => $repository->companies(
                $filters,
                $page === false ? 1 : (int) $page
            ),
            'filters' => $filters,
            'locations' => $repository->activeLocations(),
            'companyTypes' => DomainRules::COMPANY_TYPES,
        ]));
    }

    public function show(Request $request, string $slug): Response
    {
        $repository = new MarketplaceRepository();
        $company = $repository->companyBySlug($slug);

        if ($company === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Company not found']),
                404
            );
        }

        return Response::html(View::render('company/show', [
            'pageTitle' => (string) $company['name'],
            'activeArea' => 'company',
            'company' => $company,
            'gallery' => $repository->gallery('company', (int) $company['id']),
            'projects' => $repository->companyProjects((int) $company['id'], 6),
            'listings' => $repository->companyListings((int) $company['id'], 6),
        ]));
    }
}
