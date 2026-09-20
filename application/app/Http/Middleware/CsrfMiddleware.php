<?php

declare(strict_types=1);

namespace Barama\Http\Middleware;

use Barama\Core\Csrf;
use Barama\Core\MiddlewareInterface;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->input('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            return Response::html(
                View::render('errors/419', [
                    'pageTitle' => 'Session expired',
                    'activeArea' => '',
                ]),
                419
            );
        }

        return $next($request);
    }
}
