<?php

declare(strict_types=1);

namespace Barama\Core;

final class Application
{
    public function __construct(
        private readonly Router $router
    ) {
    }

    public function run(): void
    {
        $request = Request::capture();
        $response = $this->router->dispatch($request);
        $response->send();
    }
}
