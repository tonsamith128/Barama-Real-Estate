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

$seenExact = [];
$seenPattern = [];
$registered = [];

foreach ($routes as $route) {
    $method = strtoupper((string) ($route['method'] ?? ''));
    $path = (string) ($route['path'] ?? '');
    $handler = $route['handler'] ?? null;
    $middleware = $route['middleware'] ?? null;

    if (
        $method === ''
        || $path === ''
        || !str_starts_with($path, '/')
        || ($path !== '/' && str_ends_with($path, '/'))
    ) {
        throw new RuntimeException('A route has an invalid method or path.');
    }

    $key = $method . ' ' . $path;

    if (isset($seenExact[$key])) {
        throw new RuntimeException('Duplicate route detected: ' . $key);
    }

    $seenExact[$key] = true;
    $registered[] = $key;

    preg_match_all(
        '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
        $path,
        $parameterMatches
    );

    $parameters = $parameterMatches[1] ?? [];

    if (count($parameters) !== count(array_unique($parameters))) {
        throw new RuntimeException(
            'Route contains a duplicate path parameter: ' . $key
        );
    }

    $patternPath = preg_replace(
        '/\{[A-Za-z_][A-Za-z0-9_]*\}/',
        '{}',
        $path
    );

    if (!is_string($patternPath)) {
        throw new RuntimeException(
            'Route pattern could not be normalized: ' . $key
        );
    }

    $patternKey = $method . ' ' . $patternPath;

    if (isset($seenPattern[$patternKey])) {
        throw new RuntimeException(
            'Ambiguous route pattern detected: '
            . $seenPattern[$patternKey]
            . ' conflicts with '
            . $key
        );
    }

    $seenPattern[$patternKey] = $key;

    if (
        is_array($handler)
        && count($handler) === 2
        && is_string($handler[0])
        && is_string($handler[1])
    ) {
        [$class, $action] = $handler;

        if (!class_exists($class)) {
            throw new RuntimeException(
                'Route controller class does not exist: '
                . $key
                . ' -> '
                . $class
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

        $controllerMethod = new ReflectionMethod($class, $action);

        if (!$controllerMethod->isPublic()) {
            throw new RuntimeException(
                'Route controller method is not public: '
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
        throw new RuntimeException(
            'Route middleware collection is invalid: ' . $key
        );
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
    'POST /locale',
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
    'GET /verify-email/{token}',
    'POST /verify-email/{token}',
    'GET /forgot-password',
    'POST /forgot-password',
    'GET /reset-password/{token}',
    'POST /reset-password/{token}',
    'POST /logout',
    'GET /account',
    'GET /account/{section}',
    'POST /account/profile',
    'POST /account/security/password',
    'POST /account/security/email',
    'POST /account/security/deactivate',
    'POST /account/company/onboard',
    'POST /account/company-invitations/{id}/accept',
    'GET /developer',
    'GET /developer/{section}',
    'GET /developer/manage/{resource}/{id}',
    'POST /developer/companies',
    'POST /developer/projects',
    'POST /developer/properties',
    'POST /developer/listings',
    'POST /developer/inquiries/{id}/lead',
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
    . ' unique routes, semantic patterns, public controller methods, '
    . 'middleware contracts, path parameters and critical surfaces verified.'
    . PHP_EOL
);
