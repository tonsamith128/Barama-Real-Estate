<?php

declare(strict_types=1);

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Http\Controllers\AccountController;
use Barama\Http\Controllers\CompanyController;
use Barama\Http\Controllers\DashboardController;
use Barama\Http\Controllers\DeveloperController;
use Barama\Http\Controllers\HomeController;

$router->get('/', [HomeController::class, 'index']);
$router->get('/account', [AccountController::class, 'index']);
$router->get('/company', [CompanyController::class, 'index']);
$router->get('/company/{slug}', [CompanyController::class, 'show']);
$router->get('/developer', [DeveloperController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/health', static function (Request $request): Response {
    return Response::json([
        'status' => 'ok',
        'application' => (string) config('app.name'),
        'version' => APP_VERSION,
    ]);
});
