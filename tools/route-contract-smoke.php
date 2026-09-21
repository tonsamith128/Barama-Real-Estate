<?php

declare(strict_types=1);

use Barama\Core\Router;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/runtime.php';

$router = new Router();

require APP_PATH . '/routes/web.php';

$reflection = new ReflectionClass($router);
$property = $reflection->getProperty('routes');
$property->setAccessible(true);

/** @var array<int,array<string,mixed>> $routes */
$routes = $property->getValue($router);

if ($routes === []) {
    throw new RuntimeException('No application routes were registered.');
}

$seen = [];
$registered = [];

foreach ($routes as $route) {
    $method = strtoupper((string) ($route['method'] ?? ''));
    $path = (string) ($route['path'] ?? '');
    $handler = $route['handler'] ?? null;
    $middleware = $route['middleware'] ?? null;

    if ($method === '' || $path === '' || !str_starts_with($path, '/')) {
        throw new RuntimeException('A route has an invalid method or path.');
    }

    $key = $method . ' ' . $path;

    if (isset($seen[$key])) {
        throw new RuntimeException('Duplicate route detected: ' . $key);
    }

    $seen[$key] = true;
    $registered[] = $key;

    if (
        is_array($handler)
        && count($handler) === 2
        && is_string($handler[0])
        && is_string($handler[1])
    ) {
        [$class, $action] = $handler;

        if (!class_exists($class)) {
            throw new RuntimeException(
                'Route controller class does not exist: ' . $key . ' -> ' . $class
            );
        }

        if (!method_exists($class, $action)) {
            throw new RuntimeException(
                'Route controller method does not exist: '
                . $key
                . ' -> '
                . $class
                . '::'
                . $action
            );
        }
    } elseif (!is_callable($handler)) {
        throw new RuntimeException(
            'Route handler is not callable or a controller action: ' . $key
        );
    }

    if (!is_array($middleware)) {
        throw new RuntimeException('Route middleware collection is invalid: ' . $key);
    }

    foreach ($middleware as $item) {
        if (!$item instanceof Barama\Core\MiddlewareInterface) {
            throw new RuntimeException(
                'Route middleware contract is invalid: ' . $key
            );
        }
    }
}

$critical = [
    'GET /',
    'GET /properties',
    'GET /properties/{slug}',
    'GET /projects',
    'GET /projects/{slug}',
    'GET /company',
    'GET /company/{slug}',
    'GET /login',
    'POST /login',
    'GET /register',
    'POST /register',
    'GET /verify-email',
    'POST /verify-email/resend',
    'GET /forgot-password',
    'POST /forgot-password',
    'GET /account',
    'GET /account/{section}',
    'POST /account/company/onboard',
    'GET /developer',
    'GET /developer/{section}',
    'GET /developer/manage/{resource}/{id}',
    'POST /developer/companies',
    'POST /developer/projects',
    'POST /developer/properties',
    'POST /developer/listings',
    'GET /dashboard',
    'GET /dashboard/{section}',
    'GET /dashboard/manage/{resource}/{id}',
    'POST /dashboard/workflow/{resource}/{id}',
    'POST /dashboard/users/{id}/status',
    'POST /dashboard/users/{id}/administrator',
];

foreach ($critical as $route) {
    if (!in_array($route, $registered, true)) {
        throw new RuntimeException(
            'Critical application route is missing: ' . $route
        );
    }
}

sort($registered, SORT_STRING);

fwrite(
    STDOUT,
    '[PASS] Route contract: '
    . count($registered)
    . ' unique routes, controller methods, middleware contracts and critical surfaces verified.'
    . PHP_EOL
);
