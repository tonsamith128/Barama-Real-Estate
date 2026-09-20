<?php

declare(strict_types=1);

namespace Barama\Core;

use Closure;
use RuntimeException;

final class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     handler: callable|array{0: class-string, 1: string}
     * }>
     */
    private array $routes = [];

    public function get(string $path, callable|array $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'handler' => $handler,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $params = $this->match($route['path'], $request->path());

            if ($params === null) {
                continue;
            }

            $handler = $this->resolveHandler($route['handler']);
            $result = $handler($request, ...array_values($params));

            if ($result instanceof Response) {
                return $result;
            }

            if (is_string($result)) {
                return Response::html($result);
            }

            throw new RuntimeException('Route handlers must return a Response or HTML string.');
        }

        return Response::html(
            View::render('errors/404', ['pageTitle' => 'Page not found']),
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

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '//' ? '/' : $normalized;
    }
}
