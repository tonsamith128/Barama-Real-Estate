<?php

declare(strict_types=1);

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Http\Controllers\AccountController;
use Barama\Http\Controllers\AuthController;
use Barama\Http\Controllers\CompanyController;
use Barama\Http\Controllers\DashboardController;
use Barama\Http\Controllers\DeveloperController;
use Barama\Http\Controllers\HomeController;
use Barama\Http\Middleware\AuthMiddleware;
use Barama\Http\Middleware\CsrfMiddleware;
use Barama\Http\Middleware\GuestMiddleware;
use Barama\Http\Middleware\PermissionMiddleware;

$guest = [new GuestMiddleware()];
$guestPost = [new GuestMiddleware(), new CsrfMiddleware()];

$router->get('/', [HomeController::class, 'index']);

$router->get('/company', [CompanyController::class, 'index']);
$router->get('/company/{slug}', [CompanyController::class, 'show']);

$router->get('/login', [AuthController::class, 'showLogin'], $guest);
$router->post('/login', [AuthController::class, 'login'], $guestPost);

$router->get('/register', [AuthController::class, 'showRegister'], $guest);
$router->post('/register', [AuthController::class, 'register'], $guestPost);

$router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], $guest);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink'], $guestPost);

$router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'], $guest);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword'], $guestPost);

$router->post(
    '/logout',
    [AuthController::class, 'logout'],
    [new AuthMiddleware(), new CsrfMiddleware()]
);

$router->get(
    '/account',
    [AccountController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access')]
);

$router->get(
    '/developer',
    [DeveloperController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access')]
);

$router->get(
    '/dashboard',
    [DashboardController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);

$router->get('/health', static function (Request $request): Response {
    return Response::json([
        'status' => 'ok',
        'application' => (string) config('app.name'),
        'version' => APP_VERSION,
    ]);
});
