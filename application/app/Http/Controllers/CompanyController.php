<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;

final class CompanyController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('company/index', [
            'pageTitle' => 'Real Estate Developers',
            'activeArea' => 'company',
        ]));
    }

    public function show(Request $request, string $slug): Response
    {
        return Response::html(View::render('company/show', [
            'pageTitle' => ucwords(str_replace('-', ' ', $slug)),
            'activeArea' => 'company',
            'companySlug' => $slug,
        ]));
    }
}
