<?php

declare(strict_types=1);

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Http\Controllers\AccountController;
use Barama\Http\Controllers\AdminManagementController;
use Barama\Http\Controllers\AuthController;
use Barama\Http\Controllers\CompanyController;
use Barama\Http\Controllers\DashboardController;
use Barama\Http\Controllers\DeveloperController;
use Barama\Http\Controllers\DeveloperManagementController;
use Barama\Http\Controllers\HomeController;
use Barama\Http\Controllers\InquiryController;
use Barama\Http\Middleware\AuthMiddleware;
use Barama\Http\Middleware\CsrfMiddleware;
use Barama\Http\Middleware\GuestMiddleware;
use Barama\Http\Middleware\PermissionMiddleware;

$guest = [new GuestMiddleware()];
$guestPost = [new GuestMiddleware(), new CsrfMiddleware()];
$csrf = [new CsrfMiddleware()];

$router->get('/', [HomeController::class, 'index']);

$router->get('/company', [CompanyController::class, 'index']);
$router->get('/company/{slug}', [CompanyController::class, 'show']);

$router->post('/inquiries', [InquiryController::class, 'store'], $csrf);

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
    '/developer/{section}',
    [DeveloperController::class, 'section'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access')]
);

$router->post(
    '/developer/companies',
    [DeveloperManagementController::class, 'createCompany'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/companies/{id}',
    [DeveloperManagementController::class, 'updateCompany'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/companies/{id}/members',
    [DeveloperManagementController::class, 'addMember'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/companies/{id}/members/{userId}/deactivate',
    [DeveloperManagementController::class, 'deactivateMember'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/projects',
    [DeveloperManagementController::class, 'createProject'],
    [new AuthMiddleware(), new PermissionMiddleware('project.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/projects/{id}',
    [DeveloperManagementController::class, 'updateProject'],
    [new AuthMiddleware(), new PermissionMiddleware('project.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/properties',
    [DeveloperManagementController::class, 'createProperty'],
    [new AuthMiddleware(), new PermissionMiddleware('property.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/properties/{id}',
    [DeveloperManagementController::class, 'updateProperty'],
    [new AuthMiddleware(), new PermissionMiddleware('property.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/listings',
    [DeveloperManagementController::class, 'createListing'],
    [new AuthMiddleware(), new PermissionMiddleware('listing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/listings/{id}',
    [DeveloperManagementController::class, 'updateListing'],
    [new AuthMiddleware(), new PermissionMiddleware('listing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/workflow/{resource}/{id}',
    [DeveloperManagementController::class, 'workflow'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new CsrfMiddleware()]
);
$router->post(
    '/developer/inquiries/{id}/lead',
    [DeveloperManagementController::class, 'convertLead'],
    [new AuthMiddleware(), new PermissionMiddleware('lead.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/media',
    [DeveloperManagementController::class, 'uploadMedia'],
    [new AuthMiddleware(), new PermissionMiddleware('media.manage'), new CsrfMiddleware()]
);

$router->get(
    '/dashboard',
    [DashboardController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);
$router->get(
    '/dashboard/{section}',
    [DashboardController::class, 'section'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);
$router->post(
    '/dashboard/locations',
    [AdminManagementController::class, 'createLocation'],
    [new AuthMiddleware(), new PermissionMiddleware('location.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/amenities',
    [AdminManagementController::class, 'createAmenity'],
    [new AuthMiddleware(), new PermissionMiddleware('amenity.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/workflow/{resource}/{id}',
    [AdminManagementController::class, 'workflow'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

$router->get('/health', static function (Request $request): Response {
    return Response::json([
        'status' => 'ok',
        'application' => (string) config('app.name'),
        'version' => APP_VERSION,
    ]);
});
