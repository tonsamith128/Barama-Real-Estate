<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;

final class HomeController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('home', [
            'pageTitle' => 'Barama Real Estate',
            'activeArea' => 'home',
        ]));
    }
}
