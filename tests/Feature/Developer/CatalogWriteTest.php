<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function firm(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Approved '.$slug,
            'slug' => $slug,
            'status' => 'approved',
            'published_at' => now()->subHour(),
        ]);
    }

    private function member(Company $company, string $role = 'owner'): User
    {
        $user = User::factory()->create();
        CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => 'active',
        ]);
        return $user;
    }

    private function data(string $reference = 'DRAFT-001'): array
    {
        return [
            'reference_code' => $reference,
            'title' => 'Private Test Villa',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'price' => '150000.00',
            'currency' => 'USD',
        ];
    }

    public function test_owner_creates_private_draft_before_admin_publication(): void
    {
        $company = $this->firm('agency-one');
        $owner = $this->member($company);
        $this->actingAs($owner)
            ->post(route('developer.properties.store', $company), $this->data())
            ->assertRedirect(route('developer.properties.index', $company));
        $property = Property::query()->firstOrFail();
        $listing = Listing::query()->firstOrFail();
        $this->assertSame('draft', $property->status);
        $this->assertSame('draft', $listing->status);
        $this->get('/properties')->assertDontSee('Private Test Villa');

        $this->post(route('developer.properties.submit', [$company, $property]))
            ->assertRedirect();
        $property->refresh();
        $listing->refresh();
        $this->assertSame('pending', $property->status);
        $this->assertSame('pending', $listing->status);
        $this->get('/properties')->assertDontSee('Private Test Villa');

        $administrator = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($administrator)
            ->post(route('admin.catalog.approve', $listing))
            ->assertRedirect();
        $this->assertSame('approved', $property->fresh()->status);
        $this->assertSame('published', $listing->fresh()->status);
        $this->get('/properties')->assertSee('Private Test Villa');
        $this->assertDatabaseCount('audit_events', 3);
    }

    public function test_other_company_and_read_only_member_cannot_write(): void
    {
        $company = $this->firm('agency-two');
        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->post(route('developer.properties.store', $company), $this->data())
            ->assertForbidden();
        $reader = $this->member($company, 'member');
        $this->actingAs($reader)
            ->post(route('developer.properties.store', $company), $this->data())
            ->assertForbidden();
        $this->assertDatabaseCount('properties', 0);
    }

    public function test_another_company_project_is_rejected(): void
    {
        $company = $this->firm('agency-three');
        $other = $this->firm('agency-four');
        $owner = $this->member($company);
        $project = Project::query()->create([
            'company_id' => $other->getKey(),
            'name' => 'Wrong project',
            'slug' => 'wrong-project',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $this->actingAs($owner)
            ->post(route('developer.properties.store', $company),
                array_merge($this->data(), ['project_id' => $project->getKey()]))
            ->assertSessionHasErrors(['project_id']);
        $this->assertDatabaseCount('properties', 0);
    }

    public function test_only_super_admin_approves_listings(): void
    {
        $company = $this->firm('agency-five');
        $owner = $this->member($company);
        $this->actingAs($owner)
            ->post(route('developer.properties.store', $company), $this->data());
        $listing = Listing::query()->firstOrFail();
        $this->post(route('admin.catalog.approve', $listing))->assertForbidden();
        $this->assertSame('draft', $listing->fresh()->status);
    }

    public function test_super_admin_assigns_verified_company_owner(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($admin)
            ->post(route('admin.catalog.companies.create'), [
                'name' => 'Verified Agency',
                'slug' => 'verified-agency',
                'owner_email' => $owner->email,
            ])->assertRedirect(route('admin.catalog.index'));

        $firm = Company::query()->where('slug', 'verified-agency')->firstOrFail();
        $this->assertSame('approved', $firm->status);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $firm->getKey(),
            'user_id' => $owner->getKey(),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }
}
