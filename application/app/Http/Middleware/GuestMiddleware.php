<?php

declare(strict_types=1);

namespace Barama\Http\Middleware;

use Barama\Core\MiddlewareInterface;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\AuthService;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $auth = new AuthService();

        if ($auth->check()) {
            return Response::redirect(url($auth->homePath()));
        }

        return $next($request);
    }
}
