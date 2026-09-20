<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;

final class AccountController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('account/index', [
            'pageTitle' => 'User Account',
            'activeArea' => 'account',
        ]));
    }
}
