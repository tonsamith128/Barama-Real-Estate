<?php

declare(strict_types=1);

namespace Barama\Http\Middleware;

use Barama\Core\MiddlewareInterface;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\AuthService;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $permission
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $auth = new AuthService();

        if (!$auth->check()) {
            return Response::redirect(url('/login'));
        }

        $workspacePermissions = [
            'account.access' => 'account',
            'developer.access' => 'developer',
            'dashboard.access' => 'dashboard',
        ];

        $allowed = isset($workspacePermissions[$this->permission])
            ? $auth->canAccessWorkspace($workspacePermissions[$this->permission])
            : $auth->can($this->permission);

        if (!$allowed) {
            return Response::html(
                View::render('errors/403', [
                    'pageTitle' => 'Access denied',
                    'activeArea' => '',
                ]),
                403
            );
        }

        return $next($request);
    }
}
