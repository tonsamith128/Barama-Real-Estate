<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class FoundationHomeTest extends TestCase
{
    use RefreshDatabase;
    public function test_homepage_renders(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('Find your next place to call home');
    }
}
