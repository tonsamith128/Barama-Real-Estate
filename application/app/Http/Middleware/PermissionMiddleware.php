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

        $path = $request->path();
        $workspace = null;
        if ($path === '/developer' || str_starts_with($path, '/developer/')) {
            $workspace = 'developer';
        } elseif ($path === '/account' || str_starts_with($path, '/account/')) {
            $workspace = 'account';
        }
        $workspaceAllowed = $workspace === null || $auth->canAccessWorkspace($workspace);
        $allowed = $workspaceAllowed && (
            isset($workspacePermissions[$this->permission])
                ? $auth->canAccessWorkspace($workspacePermissions[$this->permission])
                : $auth->can($this->permission)
        );
        if (!$allowed && $workspace === 'account'
            && $request->method() === 'GET'
            && $auth->canAccessWorkspace('developer')) {
            return Response::redirect(url('/developer'));
        }
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
