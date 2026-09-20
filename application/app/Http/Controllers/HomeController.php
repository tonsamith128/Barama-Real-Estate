<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\MarketplaceRepository;

final class HomeController
{
    public function index(Request $request): Response
    {
        $repository = new MarketplaceRepository();
        $sale = $repository->listings(['offer' => 'sale'], 1, 1);
        $rent = $repository->listings(['offer' => 'rent'], 1, 1);
        $projects = $repository->projects([], 1, 1);
        $companies = $repository->companies([], 1, 1);

        return Response::html(View::render('home', [
            'pageTitle' => 'Barama Real Estate',
            'activeArea' => 'home',
            'latestListings' => $repository->listings([], 1, 6)['rows'],
            'latestProjects' => $repository->projects([], 1, 3)['rows'],
            'featuredCompanies' => $repository->companies([], 1, 3)['rows'],
            'locations' => $repository->activeLocations(),
            'marketStats' => [
                'sale' => (int) $sale['total'],
                'rent' => (int) $rent['total'],
                'projects' => (int) $projects['total'],
                'companies' => (int) $companies['total'],
            ],
        ]));
    }
}
