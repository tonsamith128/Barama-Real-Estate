<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class FoundationHomeTest extends TestCase
{
    public function test_homepage_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Laravel modernization foundation');
    }
}

