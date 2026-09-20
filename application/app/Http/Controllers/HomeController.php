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

        return Response::html(View::render('home', [
            'pageTitle' => 'Barama Real Estate',
            'activeArea' => 'home',
            'latestListings' => $repository->listings([], 1, 6)['rows'],
            'latestProjects' => $repository->projects([], 1, 3)['rows'],
            'featuredCompanies' => $repository->companies([], 1, 3)['rows'],
            'locations' => $repository->activeLocations(),
        ]));
    }
}
