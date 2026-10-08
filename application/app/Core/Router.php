<?php

declare(strict_types=1);

namespace Barama\Core;

use Barama\Http\Middleware\ChangeConfirmationMiddleware;
use Barama\Services\SecurityProtectionService;
use Closure;
use RuntimeException;

final class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     handler: callable|array{0: class-string, 1: string},
     *     middleware: array<int, MiddlewareInterface>
     * }>
     */
    private array $routes = [];

    /** @param array<int, MiddlewareInterface> $middleware */
    public function get(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    /** @param array<int, MiddlewareInterface> $middleware */
    public function post(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    /** @param array<int, MiddlewareInterface> $middleware */
    public function add(
        string $method,
        string $path,
        callable|array $handler,
        array $middleware = []
    ): self {
        foreach ($middleware as $item) {
            if (!$item instanceof MiddlewareInterface) {
                throw new RuntimeException('Route middleware must implement MiddlewareInterface.');
            }
        }

        $normalizedMethod = strtoupper($method);
        $normalizedPath = $this->normalizePath($path);

        if (
            $normalizedMethod === 'POST'
            && $this->isWorkspaceMutationPath($normalizedPath)
        ) {
            $middleware[] = new ChangeConfirmationMiddleware();
        }

        $this->routes[] = [
            'method' => $normalizedMethod,
            'path' => $normalizedPath,
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        try {
            $blocked = (new SecurityProtectionService())->guard($request);
            if ($blocked instanceof Response) {
                return $blocked;
            }
        } catch (\Throwable) {
            // Protection must fail open if its configuration store is unavailable.
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $params = $this->match($route['path'], $request->path());

            if ($params === null) {
                continue;
            }

            $handler = $this->resolveHandler($route['handler']);

            $destination = static function (Request $currentRequest) use ($handler, $params): Response {
                $result = $handler($currentRequest, ...array_values($params));

                if ($result instanceof Response) {
                    return $result;
                }

                if (is_string($result)) {
                    return Response::html($result);
                }

                throw new RuntimeException('Route handlers must return a Response or HTML string.');
            };

            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                static function (callable $next, MiddlewareInterface $middleware): callable {
                    return static fn (Request $currentRequest): Response
                        => $middleware->handle($currentRequest, $next);
                },
                $destination
            );

            return $pipeline($request);
        }

        return Response::html(
            View::render('errors/404', [
                'pageTitle' => 'Page not found',
                'metaDescription' => 'The requested page could not be found on Barama Real Estate.',
                'metaRobots' => 'noindex,follow',
                'activeArea' => '',
            ]),
            404
        );
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function resolveHandler(callable|array $handler): callable
    {
        if ($handler instanceof Closure || is_callable($handler)) {
            return $handler;
        }

        [$class, $method] = $handler;
        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new RuntimeException("Controller method [{$class}::{$method}] does not exist.");
        }

        return [$controller, $method];
    }

    /** @return array<string, string>|null */
    private function match(string $routePath, string $requestPath): ?array
    {
        if ($routePath === '/' && $requestPath === '/') {
            return [];
        }

        $routeSegments = explode('/', trim($routePath, '/'));
        $requestSegments = explode('/', trim($requestPath, '/'));

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];

        foreach ($routeSegments as $index => $segment) {
            $requestSegment = $requestSegments[$index] ?? '';

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches) === 1) {
                if ($requestSegment === '') {
                    return null;
                }

                $params[$matches[1]] = $requestSegment;
                continue;
            }

            if ($segment !== $requestSegment) {
                return null;
            }
        }

        return $params;
    }

    private function isWorkspaceMutationPath(string $path): bool
    {
        foreach (['/dashboard', '/developer', '/account'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '//' ? '/' : $normalized;
    }
}
