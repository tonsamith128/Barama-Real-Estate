<?php

declare(strict_types=1);

namespace Barama\Http\Middleware;

use Barama\Core\MiddlewareInterface;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\Session;
use Barama\Services\AuthService;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $auth = new AuthService();

        if ($auth->check()) {
            return $next($request);
        }

        if ($request->method() === 'GET') {
            Session::put('auth.intended', $request->path());
        }

        return Response::redirect(url('/login'));
    }
}
