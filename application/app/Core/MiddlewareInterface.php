<?php

declare(strict_types=1);

namespace Barama\Core;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
