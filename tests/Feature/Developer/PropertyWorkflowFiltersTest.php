<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PropertyWorkflowFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_filters_match_only_members_company_properties_and_preserve_query(): void
    {
        $owner = User::factory()->create();
        $company = Company::query()->create([
            'name' => 'Filtered Agency', 'slug' => 'filtered-agency',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        $other = Company::query()->create([
            'name' => 'Another Agency', 'slug' => 'another-filtered-agency',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'role' => 'manager', 'status' => 'active',
        ]);
        foreach ([
            [$company, 'Luxury Villa', 'F-001', 'draft'],
            [$company, 'Studio Rental', 'F-002', 'pending'],
            [$company, 'Approved Villa', 'F-003', 'approved'],
            [$other, 'Other Villa', 'F-004', 'draft'],
        ] as [$firm, $title, $ref, $status]) {
            Property::query()->create([
                'company_id' => $firm->id, 'title' => $title,
                'reference_code' => $ref, 'property_type' => 'villa',
                'status' => $status,
            ]);
        }

        $this->actingAs($owner)->get(route('developer.properties.index', [
            'company' => $company, 'q' => 'Villa', 'status' => 'draft',
        ]))->assertOk()
            ->assertSee('Luxury Villa')
            ->assertDontSee('Approved Villa')
            ->assertDontSee('Studio Rental')
            ->assertDontSee('Other Villa')
            ->assertSee('1 matching properties')
            ->assertSee('name="q"', false)
            ->assertSee('name="status"', false);

        $this->get(route('developer.properties.index', [
            'company' => $company, 'status' => 'wrong',
        ]))->assertSessionHasErrors('status');
    }

    public function test_unrelated_company_and_read_only_member_cannot_open_property_filters(): void
    {
        $company = Company::query()->create([
            'name' => 'Restricted Agency', 'slug' => 'restricted-agency-filter',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        $reader = User::factory()->create();
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $reader->id,
            'role' => 'member', 'status' => 'active',
        ]);
        $this->actingAs($reader)->get(route('developer.properties.index', $company))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('developer.properties.index', $company))
            ->assertForbidden();
    }
}
