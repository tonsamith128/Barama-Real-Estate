<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicDetailSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_published_property_has_canonical_social_and_safe_offer_metadata(): void
    {
        $company = $this->company();
        $property = Property::query()->create([
            'company_id' => $company->id,
            'title' => 'Riverfront Villa',
            'reference_code' => 'SEO-RIVER-01',
            'property_type' => 'villa',
            'description' => '<p>Quiet family residence &amp; beautiful river views.</p>',
            'status' => 'approved',
        ]);
        Listing::query()->create([
            'property_id' => $property->id,
            'slug' => 'riverfront-villa',
            'transaction_type' => 'sale',
            'status' => 'published',
            'price' => 195000,
            'currency' => 'USD',
            'published_at' => now()->subDay(),
        ]);

        $canonical = route('properties.show', 'riverfront-villa');
        $this->get('/properties/riverfront-villa')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
            ->assertSee('property="og:title" content="Riverfront Villa — Barama Real Estate"', false)
            ->assertSee('name="twitter:card" content="summary"', false)
            ->assertSee('Quiet family residence &amp; beautiful river views.', false)
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"priceCurrency":"USD"', false)
            ->assertDontSee('<p>Quiet family residence', false);
    }

    public function test_public_project_and_company_have_distinct_canonicals_and_safe_entities(): void
    {
        $company = $this->company();
        Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Riverfront Gardens',
            'slug' => 'riverfront-gardens',
            'project_type' => 'residential',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/projects/riverfront-gardens')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('projects.show', 'riverfront-gardens').'">', false)
            ->assertSee('"@type":"Place"', false);

        $this->get('/company/seo-approved')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('companies.show', 'seo-approved').'">', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertDontSee('name="twitter:card" content="summary_large_image"', false);
    }

    public function test_hidden_records_do_not_emit_public_detail_seo(): void
    {
        $company = $this->company();
        Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Secret Project',
            'slug' => 'secret-project',
            'project_type' => 'residential',
            'status' => 'draft',
        ]);
        $this->get('/projects/secret-project')->assertNotFound();

        $company->update(['status' => 'suspended']);
        $this->get('/company/seo-approved')->assertNotFound();
    }

    public function test_structured_json_does_not_allow_encoded_script_closing_breakout(): void
    {
        $company = $this->company([
            'description' => 'Licensed &lt;/script&gt;&lt;script&gt;injected()&lt;/script&gt; firm',
        ]);
        $response = $this->get('/company/'.$company->slug)->assertOk();

        $response->assertDontSee('</script><script>injected()', false)
            ->assertSee('\u003C/script\u003E', false);
    }

    private function company(array $overrides = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => 'SEO Approved Realty',
            'slug' => 'seo-approved',
            'company_type' => 'agency',
            'description' => 'Locally owned real estate company.',
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ], $overrides));
    }
}
