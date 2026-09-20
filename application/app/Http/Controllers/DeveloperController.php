<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;

final class DeveloperController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('developer/index', [
            'pageTitle' => 'Developer Account',
            'activeArea' => 'developer',
        ]));
    }
}
