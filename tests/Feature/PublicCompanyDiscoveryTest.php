<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PublicCompanyDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests render Blade before the independent CI Vite build.
        $this->withoutVite();
    }

    public function test_directory_filters_company_names_and_types_and_shows_published_counts(): void
    {
        $agency = $this->company('Meridian Realty', 'agency', true);
        $this->company('Tonle Vista Developments', 'developer', true);
        $this->company('Meridian Private Draft', 'agency', false);

        $this->project($agency, 'Approved Meridian Project', true);
        $this->project($agency, 'Private Meridian Project', false);
        $this->listing($agency, 'Approved Meridian Apartment', true);
        $this->listing($agency, 'Private Meridian Apartment', false);

        $this->get('/company?q=Meridian&type=agency')
            ->assertOk()
            ->assertSeeText('Meridian Realty')
            ->assertSeeText('1 published projects')
            ->assertSeeText('1 active listings')
            ->assertDontSeeText('Meridian Private Draft')
            ->assertDontSeeText('Tonle Vista Developments');

        $this->get('/company?type=developer')
            ->assertOk()
            ->assertSeeText('Tonle Vista Developments')
            ->assertDontSeeText('Meridian Realty');
    }

    public function test_company_public_profile_only_shows_published_related_inventory(): void
    {
        $agency = $this->company('Meridian Realty', 'agency', true);
        $this->project($agency, 'Approved Meridian Project', true);
        $this->project($agency, 'Private Meridian Project', false);
        $this->listing($agency, 'Approved Meridian Apartment', true);
        $this->listing($agency, 'Private Meridian Apartment', false);

        $this->get('/company/meridian-realty')
            ->assertOk()
            ->assertSeeText('Approved Meridian Project')
            ->assertSeeText('Approved Meridian Apartment')
            ->assertDontSeeText('Private Meridian Project')
            ->assertDontSeeText('Private Meridian Apartment');
    }

    public function test_unpublished_company_profile_is_not_public(): void
    {
        $this->company('Private Realty', 'agency', false);
        $this->get('/company/private-realty')->assertNotFound();
    }

    public function test_invalid_company_filters_redirect_with_validation_errors(): void
    {
        $this->get('/company?type=privileged_internal')->assertRedirect();
        $this->get('/company?sort=unvalidated_column')->assertRedirect();
    }

    private function company(string $name, string $type, bool $published): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'company_type' => $type,
            'status' => $published ? 'approved' : 'draft',
            'published_at' => $published ? now()->subDay() : null,
        ]);
    }

    private function project(Company $company, string $name, bool $published): Project
    {
        return Project::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'project_type' => 'residential',
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? now()->subDay() : null,
        ]);
    }

    private function listing(Company $company, string $name, bool $published): Listing
    {
        $property = Property::query()->create([
            'company_id' => $company->id,
            'reference_code' => 'COMPANY-'.strtoupper(substr(md5($name), 0, 12)),
            'title' => $name,
            'property_type' => 'apartment',
            'status' => 'approved',
            'bedrooms' => 2,
            'bathrooms' => 1,
        ]);

        return Listing::query()->create([
            'property_id' => $property->id,
            'slug' => Str::slug($name),
            'transaction_type' => 'sale',
            'status' => $published ? 'published' : 'draft',
            'price' => 150000,
            'currency' => 'USD',
            'published_at' => $published ? now()->subDay() : null,
        ]);
    }
}
