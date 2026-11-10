<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicDiscoveryFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_filters_match_address_and_minimum_bedrooms_without_exposing_drafts(): void
    {
        $company = $this->approvedCompany();

        $this->publishListing($company, 'Sen Sok Family Villa', 'Sen Sok, Phnom Penh', 4, true);
        $this->publishListing($company, 'Daun Penh Studio', 'Daun Penh, Phnom Penh', 0, true);
        $this->publishListing($company, 'Private Sen Sok Villa', 'Sen Sok, Phnom Penh', 5, false);

        $this->get('/properties?location=Sen%20Sok&min_bedrooms=3')
            ->assertOk()
            ->assertSeeText('Sen Sok Family Villa')
            ->assertDontSeeText('Daun Penh Studio')
            ->assertDontSeeText('Private Sen Sok Villa');

        // Location keywords work in the general search, too.
        $this->get('/properties?q=Daun%20Penh')
            ->assertOk()
            ->assertSeeText('Daun Penh Studio')
            ->assertDontSeeText('Sen Sok Family Villa');
    }

    public function test_project_filters_match_type_stage_and_company_without_exposing_drafts(): void
    {
        $company = $this->approvedCompany();

        Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Sen Sok Family Estate',
            'slug' => 'sen-sok-family-estate',
            'project_type' => 'residential',
            'completion_status' => 'under_construction',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Factory Park',
            'slug' => 'factory-park',
            'project_type' => 'industrial',
            'completion_status' => 'completed',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Private Family Estate',
            'slug' => 'private-family-estate',
            'project_type' => 'residential',
            'completion_status' => 'under_construction',
            'status' => 'draft',
        ]);

        $this->get('/projects?q=Estate&type=residential&stage=under_construction')
            ->assertOk()
            ->assertSeeText('Sen Sok Family Estate')
            ->assertDontSeeText('Factory Park')
            ->assertDontSeeText('Private Family Estate');
    }

    public function test_invalid_discovery_filters_are_rejected(): void
    {
        $this->get('/properties?min_bedrooms=-3')->assertRedirect();
        $this->get('/projects?stage=not_a_real_stage')->assertRedirect();
    }

    private function approvedCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Approved Realty',
            'slug' => 'approved-realty',
            'company_type' => 'agency',
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ]);
    }

    private function publishListing(
        Company $company,
        string $title,
        string $address,
        int $bedrooms,
        bool $published,
    ): void {
        $property = Property::query()->create([
            'company_id' => $company->id,
            'reference_code' => 'QA-'.strtoupper(substr(md5($title), 0, 12)),
            'title' => $title,
            'property_type' => 'villa',
            'status' => 'approved',
            'address_line' => $address,
            'bedrooms' => $bedrooms,
            'bathrooms' => 1,
        ]);

        Listing::query()->create([
            'property_id' => $property->id,
            'slug' => \Illuminate\Support\Str::slug($title),
            'transaction_type' => 'sale',
            'status' => $published ? 'published' : 'draft',
            'price' => 250000,
            'currency' => 'USD',
            'published_at' => $published ? now()->subDay() : null,
        ]);
    }
}
