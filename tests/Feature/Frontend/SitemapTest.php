<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_public_entities_only(): void
    {
        $company = Company::query()->create([
            'name' => 'Public Company',
            'slug' => 'public-company',
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ]);
        $privateCompany = Company::query()->create([
            'name' => 'Private Company',
            'slug' => 'private-company',
            'status' => 'pending',
            'published_at' => null,
        ]);

        Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Public Project',
            'slug' => 'public-project',
            'project_type' => 'residential',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);
        Project::query()->create([
            'company_id' => $privateCompany->id,
            'name' => 'Private Project',
            'slug' => 'private-project',
            'project_type' => 'residential',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->id,
            'title' => 'Approved Property',
            'reference_code' => 'SEO-1',
            'property_type' => 'house',
            'status' => 'approved',
        ]);
        foreach ([
            ['slug' => 'public-offer', 'status' => 'published', 'published_at' => now()->subHour()],
            ['slug' => 'draft-offer', 'status' => 'draft', 'published_at' => null],
            ['slug' => 'expired-offer', 'status' => 'published', 'published_at' => now()->subDay(), 'expires_at' => now()->subHour()],
        ] as $offer) {
            Listing::query()->create(array_merge([
                'property_id' => $property->id,
                'transaction_type' => 'sale',
                'price' => 100000,
                'currency' => 'USD',
            ], $offer));
        }

        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $response->streamedContent();

        $this->assertStringContainsString('/company/public-company', $xml);
        $this->assertStringContainsString('/projects/public-project', $xml);
        $this->assertStringContainsString('/properties/public-offer', $xml);
        $this->assertStringNotContainsString('/company/private-company', $xml);
        $this->assertStringNotContainsString('/projects/private-project', $xml);
        $this->assertStringNotContainsString('/properties/draft-offer', $xml);
        $this->assertStringNotContainsString('/properties/expired-offer', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_robots_points_to_public_sitemap_and_excludes_private_workspaces(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.route('seo.sitemap'), false)
            ->assertSee('Disallow: /dashboard')
            ->assertSee('Disallow: /account')
            ->assertSee('Disallow: /developer');
    }
}
